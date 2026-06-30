# Debrief: Utilizador Feature Slice (CRUD completo)

**Issue:** #68
**Branch:** feat/utilizador-feature-slice
**Data:** 2026-06-30
**Commits:** 15 commits (implementação) + brief/spec/plan

## O que foi implementado

CRUD completo da feature `Utilizador` (Listar, Ver, Criar, Actualizar, Eliminar) em Vertical Slice, sobre o modelo partilhado `User`, mais infra transversal de SoftDelete:

- **SoftDeletes no `User`** (migration `deleted_at` + trait + `@property-read`).
- **Permissões** `utilizadores.{ver,criar,actualizar,eliminar}` (atribuídas ao role `admin`).
- **5 Actions + FormRequests**, 2 DTOs (Criar/Actualizar), 1 Resource, 1 enum de ordenação.
- **Infra transversal nova:** enum `FiltroEstadoRegisto` + trait `FiltravelPorEstadoRegisto` (scope `filtrarPorEstadoRegisto()`), reutilizável por qualquer modelo com SoftDeletes.
- **Controller** (5 métodos, só dispatch) + `apiResource('utilizadores')` com param `{utilizador}` e `withTrashed(['show','update','destroy'])`.
- **Padrão B de eliminação** (hard delete quando sem referências; soft delete quando referenciado), suportado por FKs filhas `restrictOnDelete`.
- **Testes:** 5 Feature + 5 Action Unit + 2 DTO Unit. Suite global: 674 testes, 100% coverage e type-coverage.

## Ficheiros alterados

| Ficheiro | Tipo | Notas |
| -------- | ---- | ----- |
| `database/migrations/..._add_softdeletes_to_users_table.php` | criado | `deleted_at` em `users` |
| `database/migrations/..._seed_utilizadores_permissions.php` | criado | 4 permissões → role admin |
| `database/migrations/..._change_users_fks_to_restrict_on_delete.php` | criado | `documentos.id_responsavel` e `etapas_documento.id_utilizador`: `nullOnDelete → restrictOnDelete` |
| `app/Models/User.php` | alterado | `SoftDeletes` + `FiltravelPorEstadoRegisto` + `@property-read ?Carbon $deleted_at` |
| `app/Policies/UtilizadorPolicy.php` | alterado | `viewAny/view/create/update/delete` (view com auto-acesso) |
| `app/Shared/Enums/FiltroEstadoRegisto.php` | criado | `Todos/SomenteAtivos/SomenteInativos` |
| `app/Models/Concerns/FiltravelPorEstadoRegisto.php` | criado | scope transversal de filtro de estado |
| `app/Shared/Cache/TagCache.php` | alterado | `case Utilizadores` |
| `app/Features/Utilizador/UtilizadorResource.php` | criado | `id/name/email/roles/deleted_at/created_at` |
| `app/Features/Utilizador/Listar/*` | criado | enum + Action (cache + filtro) + Request |
| `app/Features/Utilizador/Ver/*` | criado | Action (sem cache, auto-acesso) + Request |
| `app/Features/Utilizador/Criar/*` | criado | DTO + Action + Request (Password rule); Request não-final |
| `app/Features/Utilizador/Actualizar/*` | criado | DTO + Action + Request; Request não-final |
| `app/Features/Utilizador/Eliminar/*` | criado | Action (Padrão B via pré-verificação) + Request |
| `app/Features/Utilizador/UtilizadorController.php` | alterado | 5 métodos CRUD |
| `routes/api.php` | alterado | `apiResource` + `withTrashed(['show','update','destroy'])` |
| `database/factories/UserFactory.php` | alterado | state `inativo()` |
| `tests/ArchTest.php` | alterado | ignore do enum + 2 Requests não-final |
| `tests/Unit/Models/DocumentoTest.php`, `EtapaDocumentoTest.php` | alterado | testes `nullOnDelete` → preservação em `restrictOnDelete` |
| `docs/system_spec/02-shared/soft-delete.md` | alterado | trait + binding withTrashed + nomenclatura do enum |
| `tests/Feature/Features/Utilizador/*`, `tests/Unit/Features/Utilizador/*` | criado | 12 ficheiros de teste |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| `FiltroEstadoRegisto` como **enum + trait transversal** | Match inline `Model::withTrashed()` em cada Action (como o spec previa) | Evita repetição; reutilizável por todos os modelos SoftDeletes; o scope vive no domínio |
| Nomenclatura `Todos/SomenteAtivos/SomenteInativos` | `Todos/Activos/Inactivos` | Decisão do utilizador; alinha com a nomenclatura já documentada em `soft-delete.md` |
| Eliminar = **Padrão B por pré-verificação** (`estaReferenciado()`) | `try/catch forceDelete → delete` (o Padrão B textual) | O try/catch é inviável no SQLite de testes — a violação de FK difere para o commit e escapa ao `catch` (mesmo `\Throwable`). A pré-verificação é determinística e cross-driver; `restrictOnDelete` fica como salvaguarda na BD |
| FKs filhas `nullOnDelete → restrictOnDelete` | Manter `nullOnDelete` | Sem restrição, o hard delete anularia a autoria (`id_responsavel`/`id_utilizador`); com restrição há fallback para soft delete, preservando integridade e histórico |
| `withTrashed(['show','update','destroy'])` | `['update','destroy']` apenas | `index` expõe inactivos via `?estado=`; o `show` tem de abrir o detalhe desses registos (senão lista mostra mas detalhe dá 404) |
| **Remover** invariante "último com `utilizadores.eliminar`" | Manter o guard | Recuperável via gestão de roles (quem tem `utilizadores.actualizar` reatribui); o bloqueio era sobre-protecção |
| Guard "não eliminar o próprio" **inline** (não classe `Regra*`) | Extrair `Regra*` | Uso único, é pré-condição (não orquestra Action nem corre na transação) — critério de `regras-negocio.md` não se aplica; consistente com `AtribuirRoleAction` |
| `CriarUtilizadorRequest`/`ActualizarUtilizadorRequest` **não-final** | `final` | Mockery não mocka `final`; os DTO `fromRequest` tests mockam o Request — mesmo motivo dos irmãos Categoria/Entidade |
| `password` persistida via cast `hashed` | `Hash::make()` explícito na Action | O cast é a forma canónica Laravel e é idempotente; verificado que persiste bcrypt e que o `LoginAction` valida |

## Desvios ao Plano

- **Filtro de estado adicionado** (não estava no plano #68, que adiava `FiltroEstadoRegisto`): introduzido a pedido do utilizador como infra transversal (enum + trait). Reverte parcialmente o desvio documentado no Brief.
- **Eliminação passou a Padrão B completo** (o Brief tinha-o como desvio adiado): a pedido do utilizador, com migration de FKs `restrictOnDelete`. Mecanismo final por pré-verificação (não try/catch) por imposição do ambiente de teste.
- **Invariante "último com permissão" removida** (estava no plano/spec): decisão do utilizador.
- **Anonimização RGPD** do ramo soft delete: **adiada para a Issue #73** (continua dívida técnica do Padrão B).
- `LoginAction` mantido inalterado (avaliado `Auth::attempt`; rejeitado por ser stateful/sessão, impróprio para emissão de token Sanctum).

## Aprendizagens

- **Vertical Slice + infra transversal:** o filtro de estado mostrou onde o "slice" termina e a infra partilhada começa. O scope `filtrarPorEstadoRegisto()` num trait (em `Models/Concerns`) mantém cada Action de listagem limpa e dá consistência a todos os modelos SoftDeletes — sem violar o isolamento das slices.
- **Padrão B é sensível ao driver:** a estratégia `try/catch forceDelete` assume que a violação de FK `RESTRICT` é lançada *no statement* (verdade no MySQL/InnoDB), mas no SQLite (testes, com transação aninhada do `RefreshDatabase`) a verificação difere para o commit e escapa ao `catch`. A **pré-verificação determinística** é mais robusta e testável, com o `restrictOnDelete` como rede de segurança na BD — separar "decisão de domínio" de "garantia de integridade" clarificou o desenho.
- **O cast `hashed` torna a hashing transparente e idempotente** — passar a plain à Action é correcto; `Hash::make()` explícito seria redundante. Perceber o cast evitou um "double-hash" e manteve a Action declarativa.
- **`final` vs mockabilidade:** Requests com DTO `fromRequest` testado via Mockery têm de ser não-final; os restantes (Ver/Listar/Eliminar) ficam final. O ArchTest codifica esta distinção na ignore-list — uma convenção que só fica clara ao bater nela.
- **Route model binding e SoftDeletes:** `apiResource(...)->withTrashed([...])` é o mecanismo idiomático; incluir `show` (não só update/destroy) é a coerência que falta quando a listagem já expõe inactivos.

## SYSTEM_SPEC a actualizar

- `docs/system_spec/00-index.md` — listar `FiltroEstadoRegisto` nos enums partilhados; nota do trait `FiltravelPorEstadoRegisto`; actualizar contagem de Actions/rotas da feature Utilizador.
- `docs/system_spec/01-features/utilizador.md` — passar de 1 Action (AtribuirRole) para 6; documentar CRUD, filtro de estado, Padrão B por pré-verificação.
- `docs/system_spec/02-shared/enums.md` — adicionar `FiltroEstadoRegisto`.
- `docs/system_spec/02-shared/soft-delete.md` — já actualizado (trait, binding, nomenclatura). Confirmar nota da pré-verificação vs try/catch.
- `docs/system_spec/03-models/user.md` — SoftDeletes + trait + `deleted_at` + relações implícitas referenciadas.
- `docs/system_spec/03-models/documento.md` e `etapa-documento.md` — FK `restrictOnDelete` (era `nullOnDelete`).
- `docs/system_spec/05-routes/role.md` (Rotas Role + Utilizador) — 5 rotas REST `utilizadores` + binding withTrashed.
- `openapi.yaml` — endpoints `utilizadores` (index/show/store/update/destroy).

## Verificação final

- [x] Linter a verde (Pint + Rector)
- [x] Testes a verde (674 testes, 100% coverage + type-coverage, Larastan 9, arch)
- [x] Nenhum dado sensível em logs (password nunca logada; logs só com `id`)
- [x] Nenhum segredo em código
