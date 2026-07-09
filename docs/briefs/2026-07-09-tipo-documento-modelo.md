# Brief: TipoDocumento — model layer (migration + model + factory + policy + DTOs + resource + testes)

**Issue:** #84
**Data:** 2026-07-09
**Branch:** feat/tipo-documento-modelo

## Contexto

O mecanismo de extracção (issue futura) vai precisar de saber, para cada tipo de documento concreto (fatura, recibo, fatura-serviço, ...), que dados é expectável extrair, em que `CategoriaDocumento` posicionar o documento, e se a empresa mãe (`e_empresa_aplicacao = true`) deve aparecer como fornecedor ou cliente nesse tipo — informação usada para montar a prompt de IA e validar a identificação do documento. Esta issue cria **apenas a camada de modelo** desta definição (`TipoDocumento`), seguindo o mesmo padrão de "issue de modelo" já usado em `CategoriaDocumento` (#1) e `Documento` (#45): migration, Model, Factory, Policy, DTOs (sem `fromRequest()`) e Resource — sem Actions, Controller, FormRequests ou rotas.

## O que muda

- **Migration** `create_tipos_documento_table` — tabela `tipos_documento`: `id` (uuid PK), `nome` (string 255, índice único), `descricao` (text, obrigatório), `id_categoria` (uuid FK → `categorias_documento.id`, `restrictOnDelete()`), `posicao_empresa_mae` (string 50), 4 flags booleanas `espera_*` (default `true`), `timestamps()` — sem `deleted_at`.
- **Novo enum partilhado** `App\Shared\Enums\PosicaoEmpresaMae` (`Fornecedor` = `'fornecedor'`, `Cliente` = `'cliente'`) — documentado em `02-shared/enums.md`.
- **Model** `app/Models/TipoDocumento.php` — `HasUuids`, `HasFactory`, `#[Table]`, `#[Fillable]`, `#[UsePolicy]`, `@property-read` completo, `casts()` para `PosicaoEmpresaMae` e os 4 booleans, relação `categoria(): BelongsTo` com `->withTrashed()` (mesmo padrão de `Documento::categoria()`). Sem relação inversa em `CategoriaDocumento`.
- **Factory** `TipoDocumentoFactory` — apenas estado base (sem states adicionais): `id_categoria` via `CategoriaDocumento::factory()`, `posicao_empresa_mae` aleatório entre os 2 casos, os 4 `espera_*` a `true`.
- **Policy** `TipoDocumentoPolicy` — CRUD (`viewAny`, `view`, `create`, `update`, `delete`; sem `restore`, sem SoftDelete) via `hasPermissionTo('tipos-documento.<accao>')`.
- **Migration de permissões** `seed_tipos_documento_permissions` — cria `tipos-documento.{ver,criar,actualizar,eliminar}`; `admin` recebe todas, `utilizador` só `.ver` (mesmo padrão de `seed_documentos_permissions`).
- **DTOs** `app/Features/TipoDocumento/Criar/CriarTipoDocumentoDto.php` e `app/Features/TipoDocumento/Actualizar/ActualizarTipoDocumentoDto.php` — `final readonly class`, invariantes no construtor (não-vazios trimmed para `nome`/`descricao`/`idCategoria`; **pelo menos um** dos 4 `espera_*` tem de ser `true`). Sem `fromRequest()` (adicionado na issue de lógica).
- **Resource** `app/Features/TipoDocumento/TipoDocumentoResource.php` — serializa todos os campos do contrato, incl. `tipo_movimento` derivado (`$this->categoria?->tipo_movimento?->value`, não é coluna própria) e `categoria` via `whenLoaded()` + `CategoriaDocumentoResource`.
- **Testes** — `tests/Unit/Models/TipoDocumentoTest.php`, `tests/Unit/Policies/TipoDocumentoPolicyTest.php`, `tests/Unit/Features/TipoDocumento/CriarTipoDocumentoDtoTest.php`, `tests/Unit/Features/TipoDocumento/ActualizarTipoDocumentoDtoTest.php`, `tests/Unit/Features/TipoDocumento/TipoDocumentoResourceTest.php`.
- **SYSTEM_SPEC:** `03-models/tipo-documento.md` (novo) + `00-index.md` (linha na tabela de Models) + `02-shared/enums.md` (novo enum `PosicaoEmpresaMae`) + `04-infra/autorizacao.md` (novas permissions `tipos-documento.*` na tabela e matriz role→permission).

## O que NÃO muda

- Sem Actions, Controller, FormRequests, Events, Jobs.
- Sem rotas API (`05-routes/`) nem alterações ao `openapi.yaml` — camada de modelo, sem endpoints.
- Sem Repository/interface — CRUD simples, mesmo desvio aceite em `CategoriaDocumento`/`Entidade` (documentado no critério de `04-infra/repositories.md`).
- `fromRequest()` nos DTOs não é implementado nesta issue.
- Nenhuma alteração a `Documento`, `CategoriaDocumento` ou ao mecanismo de extracção/prompt de IA — essa ligação fica para issue futura diferida.
- Sem relação inversa `hasMany` em `CategoriaDocumento` (mesmo padrão de `Documento`).

## Riscos identificados

- **Invariante cross-field só no DTO:** o "pelo menos um `espera_*` true" só está garantido no construtor do DTO nesta issue — sem `FormRequest` ainda, não há validação HTTP amigável (422); fica registado como decisão a revisitar na issue de lógica (a issue já assinala isto em "Invariantes em risco").
- **FK `id_categoria` obrigatória com `restrictOnDelete()`:** ao contrário de `documentos.id_categoria` (nullable), aqui é obrigatória — a Factory tem de criar sempre uma `CategoriaDocumento` associada (via `CategoriaDocumento::factory()`), e o teste de FK/constraint deve confirmar que apagar uma categoria referenciada por um `TipoDocumento` falha (comportamento do `restrictOnDelete()` verificado via `mcp__laravel-boost__database-schema`/migração real, não apenas assumido).
- **Coluna `posicao_empresa_mae` como `string(50)`, cast para enum novo:** seguir o padrão confirmado via `search-docs` (Eloquent Enum Casting) — cast declarado em `casts()`, nunca comparação directa a string.
- **Ausência de SoftDelete:** confirmado pela regra de `00-convencoes-models.md` (SoftDelete só em tabelas pai/transversais referenciadas por FK) — `tipos_documento` ainda não é referenciada por nenhuma tabela filha nesta issue, por isso fica sem `SoftDeletes`, coerente com o enunciado da issue ("sem `restore`, sem SoftDelete").

## Questões em aberto

Nenhuma — a issue já define o contrato completo (colunas, DTOs, Resource, Policy, critérios de aceitação). Não há decisão de arquitectura pendente para a Spec.
