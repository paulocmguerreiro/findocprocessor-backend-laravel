# Spec: Entidade — agrupar/fundir duplicados (repontar FKs + hard-delete)

**Issue:** #99
**Brief:** docs/briefs/2026-07-21-entidade-agrupar-duplicados.md
**Data:** 2026-07-21

## Requisitos funcionais

- **RF-01:** Existe um endpoint `POST /api/entidades/{principal}/agrupar-com/{secundaria}` que funde a
  entidade `secundaria` na `principal`, numa única operação atómica.
- **RF-02:** Em todas as tabelas necessárias, o **UUID da `secundaria` é substituído pelo da
  `principal`** (repontagem das FKs) antes de esta ser removida. Hoje: `documentos.id_fornecedor` e
  `documentos.id_cliente`.
- **RF-03:** Após a repontagem, a `secundaria` é **removida permanentemente (hard-delete,
  `forceDelete()`)** — não fica soft-deleted. Como todas as FKs foram repontadas, nada a referencia
  no momento da remoção.
- **RF-04:** Os papéis da `secundaria` são unidos na `principal` por OR: `principal.e_cliente ←
  principal.e_cliente || secundaria.e_cliente`; idem `e_fornecedor`. `e_empresa_aplicacao` **não** é
  unido.
- **RF-05:** A operação é **à prova de futuro**: antes do hard-delete, o sistema inspecciona o
  esquema real da BD e identifica todas as colunas FK que referenciam `entidades`. Se existir alguma
  FK fora da allow-list explícita de colunas tratadas (`documentos.id_fornecedor`,
  `documentos.id_cliente`), a operação **falha** (excepção → `422`) e faz rollback total — nunca
  remove a secundária deixando referências pendentes. O hard-delete sobre FKs `restrictOnDelete`
  fornece uma rede de segurança automática complementar (`QueryException` → rollback), mas a guarda
  explícita cobre também FKs `nullOnDelete`/`cascadeOnDelete` que a BD não bloquearia.
- **RF-06:** Em sucesso, a resposta devolve a `principal` actualizada (via `EntidadeResource`,
  HTTP 200), reflectindo os papéis unidos.
- **RF-07:** A operação fica registada no audit trail (`RegistaActividade`) ao nível da `Entidade`
  (remoção/hard-delete da secundária + update de papéis da principal). O `nif` continua excluído do
  log. (Nota de implementação: confirmar que o `forceDelete()` produz o registo de actividade
  esperado — o comportamento exacto do spatie/activitylog no `forceDelete` é validado na Fase 2.)

## Requisitos não funcionais

- **RNF-01:** Toda a mutação (repontagem + união de papéis + hard-delete + invalidação de cache)
  ocorre dentro de uma única `DB::transaction()`; qualquer falha faz rollback total (CA transaccional).
- **RNF-02:** `Gate::authorize()` fica **fora** da transação (autorização dupla camada: FormRequest +
  Action).
- **RNF-03:** A repontagem usa mass update por query builder (não carrega documentos em memória); a
  quantidade de documentos por entidade pode ser grande.
- **RNF-04:** `strict_types=1`, Larastan nível 9 sem erros, `type-coverage` e `coverage` 100%,
  ArchTest verde. Sem `mixed`; `@throws` declarado em todo o método que lança.

## Contratos de API

| Método | Path | Request | Response |
| ------ | ---- | ------- | -------- |
| POST | `/api/entidades/{principal}/agrupar-com/{secundaria}` | corpo vazio; `principal`/`secundaria` são UUIDs (RMB implícito, **sem** `withTrashed` — soft-deleted → 404) | `200` + `EntidadeResource` da `principal` |

**Respostas de erro (Problem Details, via handler existente):**

| Situação | Status | Origem |
| --- | --- | --- |
| `principal` ou `secundaria` inexistente (ou soft-deleted) | `404` | RMB → `NotFoundHttpException` |
| `principal == secundaria` | `422` | guarda de negócio (`DomainException`) |
| `secundaria` é `e_empresa_aplicacao = true` | `422` | guarda de negócio (`DomainException`) |
| FK a `entidades` não tratada detectada no esquema | `422` | guarda de futuro (`DomainException`), antes do hard-delete |
| Utilizador sem `entidades.agrupar` | `403` | `Gate::authorize` (FormRequest + Action) |
| Não autenticado | `401` | middleware `auth:sanctum` |

## Modelo de dados

Sem migration de esquema. Nova **permissão** seeded via data-migration nova (padrão dos restantes
seeds de permissões):

| Item | Valor | Notas |
| --- | --- | --- |
| Permissão | `entidades.agrupar` | Sincronizada ao role `admin`; `utilizador` **não** a tem |

## Regras de negócio

- **RN-01:** `principal` e `secundaria` têm de ser entidades distintas (`principal->id !==
  secundaria->id`) — caso contrário `422`.
- **RN-02:** A `secundaria` **não** pode ser a entidade `e_empresa_aplicacao = true` — caso contrário
  `422`. (A `principal` **pode** ser a empresa aplicação.)
- **RN-03:** Ambas as entidades têm de existir e estar **activas** (não soft-deleted) — garantido pelo
  RMB sem `withTrashed` (soft-deleted → 404).
- **RN-04:** União de papéis por OR (RF-04); `e_empresa_aplicacao` nunca é alterado por esta operação
  (não se invoca `RegraUnicidadeEmpresaMae`).
- **RN-05:** A allow-list de colunas FK tratadas é explícita e verificada contra o esquema real em
  runtime (RF-05). Uma FK nova para `entidades` obriga a actualizar deliberadamente a allow-list (e a
  lógica de repontagem) — nunca é repontada "às cegas".
- **RN-06:** A remoção da secundária é **hard-delete** (`forceDelete()`) — remoção permanente,
  **não** soft-delete e **não** o Padrão B (force-com-fallback a soft-delete). Aqui **não** há
  fallback: se o `forceDelete()` falhar por uma FK ainda a apontar, a excepção propaga e a transação
  faz rollback (a fusão nunca deixa a secundária removida com referências pendentes, nem cai
  silenciosamente para soft-delete).

## Dependências

- Issues bloqueantes: **nenhuma** — `Entidade` e `documentos` já existem; independente do pipeline.
- Motivação (não bloqueante): #98 (criação automática de entidades gera os duplicados).

## Questões resolvidas

| Questão (do Brief) | Decisão |
| ------------------ | ------- |
| Unir papéis da secundária na principal? | **Sim, via OR** (`e_cliente`/`e_fornecedor`); `e_empresa_aplicacao` nunca unido. |
| Estratégia de "à prova de futuro" das FKs? | **Guarda por introspecção do esquema em runtime** — allow-list explícita, lança e faz rollback se surgir FK não tratada; reforçada pela rede automática do hard-delete + `restrictOnDelete`. |
| Remoção da secundária: soft ou hard-delete? | **Hard-delete (`forceDelete()`)** — remoção permanente após repontar; sem fallback para soft-delete (Checkpoint B). |
| Ability de autorização? | **Nova permissão `entidades.agrupar`** (Policy `agrupar()` + seed + matriz). |

## Critérios de aceitação

> Herdados da issue — marcados *(issue)*; adicionados na Spec — *(spec)*.

- [ ] **CA-01:** `documentos` que apontavam para a `secundaria` (fornecedor e cliente) passam a
  apontar para a `principal`. *(issue)*
- [ ] **CA-02:** `secundaria` é removida permanentemente (hard-delete — deixa de existir na tabela,
  inclusive de `withTrashed()`); nenhuma referência órfã permanece. *(issue, ajustada — hard-delete
  em vez de soft-delete por decisão do Checkpoint B)*
- [ ] **CA-03:** `principal == secundaria`, entidade inexistente, ou secundária = empresa aplicação →
  erro (422/404) sem alterações persistidas. *(issue)*
- [ ] **CA-04:** Tudo dentro de uma transação (falha → rollback total). *(issue)*
- [ ] **CA-05:** Testes Unit + Feature (padrão dual). `composer test` verde (Larastan L9,
  coverage/type-coverage 100%). *(issue)*
- [ ] **CA-06:** system_spec: `01-features/entidade.md` + `05-routes/entidades.md` +
  `04-infra/autorizacao.md` + `00-index.md`. *(issue)*
- [ ] **CA-07:** Papéis `e_cliente`/`e_fornecedor` da principal reflectem o OR com os da secundária
  após a fusão; `e_empresa_aplicacao` da principal inalterado. *(spec)*
- [ ] **CA-08:** Uma FK nova para `entidades` (simulada num teste) que não esteja na allow-list faz a
  operação falhar com `422` e rollback total (a secundária **não** é removida). *(spec)*
- [ ] **CA-09:** `utilizador` sem `entidades.agrupar` recebe `403`; `admin` consegue agrupar. *(spec)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/01-features/entidade.md` — nova Action `AgruparEntidadeAction` (+ colaboradores)
  na tabela de Actions, FormRequest, Controller, e a nova peça de guarda/repontagem.
- `docs/system_spec/05-routes/entidades.md` — nova rota `POST /entidades/{principal}/agrupar-com/{secundaria}`.
- `docs/system_spec/04-infra/autorizacao.md` — nova permissão `entidades.agrupar` (lista, matriz,
  migration).
- `docs/system_spec/02-shared/regras-negocio.md` — **se** a repontagem/guarda for materializada como
  classe `Regra*` (a decidir no Plan).
- `docs/system_spec/00-index.md` — contagem de Actions/rotas da feature Entidade.
- **OpenAPI** (`./openapi.yaml`): delta = novo path `POST /entidades/{principal}/agrupar-com/{secundaria}`
  (escrita efectiva na Fase 3a).

## Verificação RGPD/NIS2

- **Dados pessoais:** `Entidade.nif` é dado fiscal — já excluído do audit trail
  (`atributosExcluidosDaActividade() → ['nif']`). A fusão não expõe nem loga o `nif`. Não se valida
  igualdade de NIF entre principal e secundária (a fusão é decisão humana consciente).
- **Superfície de ataque:** os identificadores `principal`/`secundaria` vêm do RMB (UUIDs validados);
  a repontagem nunca aceita nome de coluna vindo do cliente — a allow-list é uma constante de código
  confrontada com o esquema real. Sem SQL dinâmico com input externo. Operação protegida por permissão
  dedicada `entidades.agrupar` (só `admin`), autorização dupla camada.
