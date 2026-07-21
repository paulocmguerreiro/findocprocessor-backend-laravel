# Brief: Entidade — agrupar/fundir duplicados (repontar FKs + hard-delete)

**Issue:** #99
**Data:** 2026-07-21
**Branch:** feat/entidade-agrupar-duplicados

## Contexto

Seguimento do mecanismo de extração (#98). A criação automática de entidades pode gerar
**duplicados** da mesma entidade real com dados ligeiramente distintos (nome incompleto, NIF
mal lido pela extração). É preciso um endpoint que **funde** duas entidades: assume-se a
**principal** como a correcta, **substitui-se em todas as tabelas necessárias o UUID da secundária
pelo da principal** (repontagem das FKs) e a secundária é **removida permanentemente
(hard-delete)**. Não há fusão campo-a-campo — correcções aos dados da principal fazem-se pelo
`update` normal já existente.

A operação tem de ser **atómica** (uma transação; falha → rollback total) e **à prova de futuro**:
hoje só `documentos.id_fornecedor` e `documentos.id_cliente` referenciam `entidades` (ambas
`restrictOnDelete`). Como a repontagem é feita **antes** do hard-delete, nada referencia a
secundária no momento da remoção. O hard-delete traz de volta a rede de segurança da BD: se uma FK
nova (`restrictOnDelete`) ficar por repontar, o `forceDelete()` rebenta `QueryException` e a
transação faz rollback — a secundária nunca é removida com referências pendentes.

## O que muda

- **Nova slice** `app/Features/Entidade/Agrupar/` — nova Action de escrita, FormRequest e uma
  peça de domínio responsável por repontar as FKs.
- **Nova rota** `POST /api/entidades/{principal}/agrupar-com/{secundaria}` (dois route-model-bindings
  nomeados) + novo método no `EntidadeController` (dispatch, sem lógica).
- **Autorização** — nova ability na `EntidadePolicy` (ou reutilização de `eliminar`/`actualizar`;
  a decidir na Spec) + matriz role→permission em `04-infra/autorizacao.md`.
- **Nova excepção de domínio** (`extends DomainException` → 422 via handler existente) para as
  guardas de negócio (principal = secundária; secundária = empresa aplicação).
- **Repontagem** de `documentos.id_fornecedor` e `documentos.id_cliente` da secundária → principal
  (substituir o UUID da secundária pelo da principal; mass update por query — não dispara eventos de
  model, o que é aceitável aqui).
- **Hard-delete** da secundária após repontar (`->forceDelete()` — remoção permanente, **não**
  soft-delete e **não** o Padrão B force-com-fallback: aqui não há fallback para soft-delete; se
  `forceDelete()` falhar por FK ainda a apontar, a transação faz rollback).
- **Audit trail** — a operação fica registada via `RegistaActividade` (o `nif` já é excluído).
- **system_spec:** `01-features/entidade.md` (nova Action/slice), `05-routes/entidades.md`
  (nova rota), possivelmente `02-shared/regras-negocio.md` (se a repontagem virar `Regra*`) e
  `04-infra/autorizacao.md` (nova ability), + `00-index.md` (contagem de Actions/rotas).
- **Testes** — padrão dual (Unit em `tests/Unit/Features/Entidade/Agrupar/` + Feature via HTTP).

## O que NÃO muda

- **Não** há detecção automática de duplicados (sugestão de candidatos) — fora de âmbito, futura.
- **Não** há fusão campo-a-campo de dados (nome, nif, etc.) — só se repõem referências.
- **Não** se altera o pipeline de extração/#98 nem qualquer Action existente de Entidade.
- **Não** se toca no esquema da BD (sem migration nova) — as FKs `restrictOnDelete` já existem.
- **Não** se permite fundir para dentro/a partir da entidade `e_empresa_aplicacao` como
  **secundária** (guarda). A principal pode ser a empresa aplicação.
- A `principal` continua a ser editada pelo `update` normal — este endpoint não corrige a principal.

## Riscos identificados

- **Referências órfãs por FK não tratada (risco central da issue).** Se no futuro alguém adicionar
  uma FK para `entidades` (ex.: uma tabela `contactos.id_entidade`) e esquecer de a repontar aqui, a
  secundária seria removida deixando referências pendentes. Com **hard-delete**, uma FK
  `restrictOnDelete` por repontar faz o `forceDelete()` rebentar `QueryException` → rollback
  automático (rede de segurança da BD). Porém, uma FK `nullOnDelete`/`cascadeOnDelete` **não**
  bloquearia — daí a guarda explícita por introspecção do esquema (Questão 2), que apanha qualquer FK
  não tratada independentemente do `onDelete`, com erro claro em vez de `QueryException` opaco.
- **Consistência semântica dos papéis.** Se documentos que referenciavam a secundária como
  *fornecedor* passam a apontar para a principal, mas a principal tem `e_fornecedor = false`, fica-se
  com `documentos.id_fornecedor → principal` onde `principal.e_fornecedor = false` — inconsistente
  (ver Questão 1).
- **Mass update não dispara eventos Eloquent.** `Documento::where(...)->update([...])` não emite
  `updating`/`updated` nem regista audit trail dos documentos repontados (confirmado na doc Laravel
  13 — "Mass Updates"). Aceitável (a auditoria da operação faz-se ao nível da `Entidade`), mas há que
  registá-lo como decisão consciente.
- **Concorrência.** Duas fusões simultâneas envolvendo a mesma entidade poderiam repontar em cruz.
  Mitigado pela transação; avaliar `lockForUpdate` na Spec se for considerado necessário (tendência:
  não sobre-engenheirar — é uma operação administrativa rara).
- **NIF divergente.** As duas entidades podem ter NIFs diferentes (a razão de serem duplicados é
  extração imperfeita). O endpoint funde à mesma — assume-se decisão humana consciente de que são a
  mesma entidade real; não se valida igualdade de NIF (documentar como não-âmbito).

## Questões em aberto — RESOLVIDAS (Checkpoint A, 2026-07-21)

1. **União dos papéis da secundária na principal?** → **SIM, unir via OR.**
   `principal.e_cliente ← e_cliente OR secundaria.e_cliente` e idem `e_fornecedor`. Mantém a
   coerência semântica: documentos repontados como fornecedor exigem que a principal sirva esse
   papel. `e_empresa_aplicacao` **nunca** é unido (a secundária não pode ser empresa aplicação por
   guarda; a principal mantém o seu próprio flag).

2. **Estratégia de "à prova de futuro" para as FKs.** → **Guarda por introspecção do esquema em
   runtime.** Uma peça de domínio (`InventarioFksEntidade` ou equivalente) lê as FKs reais que
   referenciam `entidades` (via `Schema::getForeignKeys()`), compara com uma allow-list explícita das
   colunas tratadas (`documentos.id_fornecedor`, `documentos.id_cliente`) e **lança**
   `AgrupamentoInvalidoException` se aparecer uma FK fora da lista — dentro da transação, antes do
   hard-delete, garantindo rollback e zero órfãos. O hard-delete + `restrictOnDelete` é a rede de
   segurança automática complementar (ver Riscos).

3. **Ability de autorização.** → **Nova permissão `entidades.agrupar`** (ability dedicada). Novo
   método `agrupar()` na `EntidadePolicy` (`hasPermissionTo('entidades.agrupar')`), nova entrada na
   matriz role→permission (`04-infra/autorizacao.md`) e seed da permissão. Autorização dupla camada
   (FormRequest + Action).
