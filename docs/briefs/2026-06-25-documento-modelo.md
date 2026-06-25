# Brief — Issue #45: Documento — Camada de Modelo

**Data:** 2026-06-25
**Issue:** #45
**Slug:** `documento-modelo`
**Branch:** `feat/documento-modelo`
**Tipo:** feat
**Labels:** prio:p2, scope:domain, stack:laravel, type:feat

---

## Contexto

`Documento` é a entidade central do domínio: representa um documento financeiro registado
no sistema. Faz de pivot entre `Entidade` (fornecedor + cliente) e `CategoriaDocumento`
(ambos já implementados). O registo pode ser manual (todos os campos preenchidos → estado
`Processado` directo) ou automático por extracção externa (issue diferida) que inicia em
`Pendente` com dados parciais.

Esta issue cobre **apenas a camada de modelo**: migration, Model, enum `EstadoDocumento`,
state objects (Value Objects read-only), factory, policy stub, DTOs e resource — **sem lógica
de transição**. As Actions de transição, `ListarDocumentosAction`, classes `Regra*` e Events
ficam na issue de Lógica (#57). O histórico `EtapaDocumento` é a issue de modelo #56.

---

## O que vai ser construído

| Componente | Localização | Descrição |
|---|---|---|
| Migration | `database/migrations/..._create_documentos_table.php` | Tabela `documentos`: UUID PK, FKs nullable, índices, hash único |
| Enum | `app/Shared/Enums/EstadoDocumento.php` | BackedEnum string, **7 casos** PT |
| Interface | `app/Shared/States/ContratoEstadoDocumento.php` | Getters tipados comuns aos estados |
| State objects | `app/Shared/States/Documento{Pendente,AguardaEnvio,Enviado,AguardaResposta,Processado,Erro,Perigoso}.php` | 7 `final readonly class` |
| Model | `app/Models/Documento.php` | HasUuids, casts, 3 relações, `estado()`, 5 scopes |
| Factory | `database/factories/DocumentoFactory.php` | base `processado()` + states por estado |
| Policy | `app/Policies/DocumentoPolicy.php` | stub — todos os métodos `true` |
| DTO | `app/Features/Documento/Criar/CriarDocumentoManualDto.php` | registo manual, `final readonly` |
| DTO | `app/Features/Documento/Actualizar/ActualizarDocumentoDto.php` | correcção manual, `final readonly` |
| Resource | `app/Features/Documento/DocumentoResource.php` | serialização JSON (omite campos de storage) |
| Config | `config/filesystems.php` | 5 discos `local` PT |
| Testes | `tests/Unit/...` | model, factory, policy, DTOs, resource, state objects |

---

## Decisões técnicas relevantes

1. **UUID como PK** — `HasUuids` (UUIDv7). Migration usa `$table->uuid('id')->primary()`.
2. **Model não-`final`** — segue convenção real do codebase (`class Entidade`, `class CategoriaDocumento` não são `final`; o ArchTest "actions are final" não cobre Models). DTOs e state objects **são** `final readonly`.
3. **`#[Table]` / `#[Fillable]` como atributos PHP** — nunca propriedades `$table`/`$fillable` (convenção do projecto).
4. **FKs nullable + `nullOnDelete()`** — `id_fornecedor`, `id_cliente` → `entidades`; `id_categoria` → `categorias_documento`. Campos de domínio null em `Pendente` é válido por design. Usar `foreignUuid(...)->nullable()->constrained(...)->nullOnDelete()`.
5. **`hash_sha256` índice único** — previne re-registo do mesmo ficheiro em qualquer fluxo.
6. **Casts:** `status` → `EstadoDocumento::class`; `data_documento` → `'date'` (Carbon); `valor` → `'decimal:2'`.
7. **State objects via `match`** — `Documento::estado()` faz `match($this->status)` exaustivo sobre os 7 casos e devolve `ContratoEstadoDocumento`. Larastan nível 9 exige match exaustivo (sem `default`).
8. **DTOs sem `fromRequest()`** — confirmado por `padroes-dtos.md`: `fromRequest()` é adicionado na issue de Lógica (#57), quando os FormRequests existirem. Aqui só construtor + invariantes.
9. **5 discos de storage** — `entrada`, `enviado`, `processado`, `erro`, `perigoso`, todos `driver => 'local'` apontando a `storage_path('app/{nome}')`. Mapeamento 7 estados → 5 discos.
10. **Policy stub** — todos os métodos devolvem `true`, mas mantêm assinaturas com `User $utilizador` (+ `Documento` onde aplicável) para serem substituídos por `hasPermissionTo(...)` na issue de autenticação, à imagem de `EntidadePolicy`.
11. **SQLite (testes) vs MySQL (prod)** — SQLite não suporta `CHECK`; invariantes (`valor >= 0`, `hash` 64 chars) validadas em PHP (DTOs + state objects), não na migration.

---

## Decisão arquitectural documentada — sem Repository

Conforme a issue (Fora de âmbito) e o padrão já estabelecido em `ListarEntidadesAction`:
a listagem resolve-se directamente no Eloquent. **Sem Repository** — desvio deliberado e
aceite face a `CLAUDE.md`, coerente com o restante domínio. (Nesta issue de modelo nem há
Actions; fica registado para a issue de Lógica.)

---

## Descobertas técnicas (MCP `search-docs` + `database-schema`)

- **Enum cast** (`eloquent-mutators`): `'status' => EstadoDocumento::class` no método `casts()` — cast automático para/de enum. Confirmado para Laravel 13.
- **`decimal()` migration**: `$table->decimal('valor', total: 15, places: 2)` — precisão/escala. Cast `'decimal:2'`.
- **`foreignUuid()`**: cria coluna UUID; encadear `->nullable()->constrained('entidades')->nullOnDelete()`.
- **Scopes locais**: padrão `#[Scope]` (atributo) **ou** convenção `scopeWhereX(Builder $query)`. O codebase usa a convenção `scopeWhereX` com `Builder<Documento>` tipado no docblock (`Entidade` faz assim) — **seguir o codebase**, não o atributo `#[Scope]`.
- **`database-schema` (`entidades`)**: confirma `id` = `char(36)`, FKs UUID compatíveis; tabela `categorias_documento` existe. Sem FKs existentes a colidir. Engine real é **MySQL** (Docker), testes em SQLite.

---

## Critérios de aceitação (da Issue)

CA-01..CA-17 conforme issue #45. Destaques de verificação:
- CA-04: enum **7 casos** PT (sem `Desconhecido`).
- CA-06: state objects `final readonly`; expõem só os campos do estado (sem campo de feedback — esse vive no histórico #56).
- CA-07: 1 scope genérico (`whereEstado`) + 4 named (`whereProcessado`, `wherePendente`, `wherePerigoso`, `whereErro`).
- CA-10/CA-11: construtores dos DTOs lançam `\InvalidArgumentException`; `valor` aceita `0`, rejeita `< 0`; `hashSha256` != 64 chars rejeita.
- CA-12: Resource omite `disco_storage` e `nome_ficheiro_storage`.
- CA-16: **5 discos** em `config/filesystems.php`.
- CA-17: `composer test` — 100% coverage + 100% type coverage + Larastan 9 zero erros.

---

## Riscos identificados

- **Divergência de nomenclatura EN → PT (desvio aos placeholders de spec).** A issue redefine
  tudo em PT-PT alinhado com `CLAUDE.md`: Model `Documento` (`app/Models/Documento.php`, tabela
  `documentos`), enum `EstadoDocumento` com valores `PENDENTE`/`AGUARDA_ENVIO`/`ENVIADO`/
  `AGUARDA_RESPOSTA`/`PROCESSADO`/`ERRO`/`PERIGOSO`, slice `app/Features/Documento/`. Os
  placeholders existentes usam EN/misto e ficam **superados**:
  - `03-models/documento.md` (model `Document`, colunas `error_message`, `tipo_documento`, `nif_fornecedor`, `nif_cliente`) → reescrever na Fase 3a. A issue **elimina** `error_message` (feedback vai para `EtapaDocumento` #56) e os `nif_*` (vêm via relação `Entidade`).
  - `02-shared/enums.md` `DocumentStatus` (valores `PENDING`/`DONE`/`ERROR`) → substituir por `EstadoDocumento`.
  - `02-shared/estados.md` (estados EN) → reescrever com nomenclatura PT + mapeamento estado→disco.
  - Pastas scaffold `app/Features/Documents/{List,Correct,Delete,Reprocess}` (EN, plural) são legado de scaffolding; a slice real é `app/Features/Documento/` (PT, singular), coerente com `Entidade`/`CategoriaDocumento`. **Não** implementar nas pastas EN.
  → **Decisão:** a issue é autoritativa; seguir PT-PT. Actualização das specs faz-se em `/documenta-implementacao`.

- **Cast `decimal:2` devolve `string` em PHP.** `$documento->valor` é `string|null`, não `float`.
  - `@property-read string|null $valor` no Model.
  - O Resource pede `valor` como `float|null` → converter explicitamente: `$this->valor !== null ? (float) $this->valor : null`.
  - O `CriarDocumentoManualDto::$valor` é `float` (input) — fronteira string↔float a tratar com cuidado para type-coverage/Larastan 9.

- **`match` exaustivo nos state objects.** `estado()` deve cobrir os 7 casos sem `default`, senão Larastan acusa retorno potencialmente não-coberto. Cada `case` devolve a classe correspondente construída a partir de `$this`.

- **Design da interface `ContratoEstadoDocumento`.** Os 7 estados expõem conjuntos de campos
  diferentes (`Processado` = todos; `Erro`/`Perigoso` = só `id`, `disco_storage`,
  `nome_ficheiro_storage`). A interface deve declarar apenas os getters **comuns a todos**
  (`id`, `discoStorage`, `nomeFicheiroStorage`); campos extra ficam nas classes concretas que
  os têm. Confirmar o conjunto mínimo comum no Spec (Checkpoint B).

- **Cobertura 100% dos 7 state objects + 7 casos do enum.** Factory precisa de cobrir estados
  suficientes; testes do `estado()` devem exercer os 7 ramos do `match` (senão coverage < 100%).

- **`valor = 0` válido.** Não usar `if (! $valor)` nem `empty()` — usar `< 0` explícito.

---

## Questões em aberto — resolvidas no Checkpoint A

1. **Audit trail (`RegistaActividade`)?** → **DECIDIDO: adicionar** por consistência com
   `Entidade`/`CategoriaDocumento`. Incluir o trait e `atributosExcluidosDaActividade()` →
   `['hash_sha256', 'disco_storage', 'nome_ficheiro_storage']` (campos sensíveis / PII indirecta).

2. **`#[UsePolicy(DocumentoPolicy::class)]` no Model?** → **DECIDIDO: incluir** (consistência;
   prepara a issue de autenticação).

3. **Estados da factory.** → **DECIDIDO: cobrir os 7 estados** — acrescentar `aguardaEnvio()` e
   `aguardaResposta()` aos 5 da issue, garantindo 100% de cobertura dos ramos do `match` em `estado()`.

---

## Fora de âmbito

- Modelo `EtapaDocumento` (histórico/feedback de transições) — issue #56
- Actions de transição, `ListarDocumentosAction`, classes `Regra*`, Events — issue #57
- `fromRequest()` nos DTOs + DTOs de transição — issue #57
- Lógica de transição de estados e movimentação de ficheiros entre discos — issue #57
- Mecanismo externo de extracção (IA / OCR) — issue diferida
- Repository — não se justifica (listagem direct no Eloquent)
- Endpoints de API / rotas
