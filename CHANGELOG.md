# Changelog — FinDocProcessor Backend Laravel

Formato: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)

---

## [Unreleased]

### Changed (Infra)
- **Issue #77** — Migração de testes para MySQL exclusivo + Preflight + Collation
  - `phpunit.xml` passa a usar `DB_CONNECTION=mysql` / `DB_DATABASE=findocprocessor_testing`; `phpunit.mysql.xml` eliminado
  - `bin/test-preflight.sh` — novo guard que valida MySQL e Redis via `/dev/tcp` antes de correr a suite; encadeado como primeiro passo de `composer test`
  - `docker/mysql/init.sql` — collation `utf8mb4_unicode_ci` → `utf8mb4_0900_ai_ci`; GRANT alargado para `ALL ON *.*` (necessário para o paralelo criar `findocprocessor_testing_test_N`)
  - `Dockerfile` — `pdo_sqlite` removido das extensões PHP
  - CI `build-and-test` — adicionado serviço `mysql:8.4` com health check; `pdo_sqlite` → `pdo_mysql`; env vars MySQL; step `Setup MySQL grants (paralelo)` antes da suite
  - CI `docker-parity` — simplificado: `migrate:status` substituído por `php artisan about --only=Environment`
  - Migrations de FK correctivas (#70, #71, #72) eliminadas; `restrictOnDelete()` consolidado directamente em `create_documentos_table`
  - `composer test:mysql` removido (redunda com `composer test`); `test:preflight` adicionado
  - 724 testes, 100% coverage + type coverage, Larastan 9 — verde em MySQL (paralelo)

### Added
- **Issue #73** — Utilizador — Restaurar soft-deleted + RGPD Anonimização
  - **`RestaurarUtilizadorAction`** (`handle(User|int): User`) + `RestaurarUtilizadorRequest` + `UtilizadorPolicy::restore()` (reutiliza `utilizadores.eliminar`); invariantes `! trashed()` e email `anonimizado+` → `DomainException` (422)
  - **`AnonimizarUtilizadorAction`** (`handle(User): void`) + `AnonimizarUtilizadorRequest` + `UtilizadorPolicy::anonimizar()` — RGPD Art. 17.º: substitui `name`/`email`/`password`/`remember_token`/`email_verified_at`, revoga tokens Sanctum e faz soft delete numa única transação; invariantes auto-anonimização e já-anonimizado → 422
  - Rotas `PATCH /api/utilizadores/{id}/restaurar` (com `->withTrashed()`) e `POST /api/utilizadores/{id}/anonimizar` (204)
  - Migration `seed_utilizadores_anonimizar_permission` — permissão `utilizadores.anonimizar` atribuída ao role `admin`
  - **`User` passa a ser auditado** (trait `RegistaActividade`): CRUD normal regista `name`/`email`; `password`/`remember_token` excluídos. Anonimização usa `saveQuietly()` + evento manual `rgpd.anonimizacao` **sem PII** (o `saveQuietly()` suprime o `updated` automático que gravaria os valores antigos)
  - 724 testes, 100% cobertura + type coverage, Larastan 9
- **Issue #71** — Entidade — lógica de SoftDelete (restaurar + listagem filtrada + Padrão B)
  - **`RestaurarEntidadeAction`** (`handle(Entidade|string): Entidade`) + `RestaurarEntidadeRequest` + `EntidadePolicy::restore()` (reutiliza `entidades.eliminar`)
  - Rota `PATCH /api/entidades/{entidade}/restaurar` com `->withTrashed()` (RMB inclui soft-deleted); `apiResource` passa a `->withTrashed(['show','update','destroy'])`
  - Trait `FiltravelPorEstadoRegisto` no model `Entidade`; `ListarEntidadesAction` aceita `FiltroEstadoRegisto` (4.º param) — `GET /api/entidades?estado=todos|somente_ativos|somente_inativos` (default `somente_ativos`); `estado` na chave de cache
  - **Padrão B** em `EliminarEntidadeAction`: `forceDelete()` (hard delete) com fallback soft delete quando referenciado
  - Migration `enforce_restrict_entidades_fk_in_documentos` — `documentos.id_fornecedor`/`id_cliente` → `restrictOnDelete` em **todos** os drivers (a de #70 saltava SQLite)
  - **Convenção RMB (#71):** controllers/FormRequests usam Route Model Binding; só as Actions aceitam `Modelo|string` (`docs/system_spec/02-shared/soft-delete.md`)
  - 696 testes, 100% cobertura, 100% type coverage, Larastan 9 — verde em SQLite **e** MySQL

### Fixed
- **Issue #71** — Padrão B (`try/catch forceDelete`) nunca fazia soft delete no `catch`: `SoftDeletes::forceDelete()` não repõe `forceDeleting=true` ao lançar, pelo que o `delete()` de fallback voltava a fazer hard delete e relançava (erro 500 em prod/MySQL quando a entidade estava referenciada). Corrigido com `fresh()?->delete()` em `EliminarEntidadeAction` **e** `EliminarUtilizadorAction` (#68)


  - SoftDeletes no `User`: migration `add_softdeletes_to_users_table` (`deleted_at`); trait `SoftDeletes` + `@property-read ?Carbon $deleted_at`; `UserFactory::inativo()`
  - Migration `seed_utilizadores_permissions` — `utilizadores.{ver,criar,actualizar,eliminar}` atribuídas ao role `admin`
  - Migration `change_users_fks_to_restrict_on_delete` — `documentos.id_responsavel` e `etapas_documento.id_utilizador` de `nullOnDelete` → `restrictOnDelete`
  - **5 Actions CRUD** (`Listar`, `Ver`, `Criar`, `Actualizar`, `Eliminar`) + FormRequests; 2 DTOs (`CriarUtilizadorDto`, `ActualizarUtilizadorDto`); `UtilizadorResource`; enum `CampoOrdenacaoUtilizadores`
  - **Infra transversal de SoftDelete**: enum `FiltroEstadoRegisto` (`Todos`/`SomenteAtivos`/`SomenteInativos`) + trait `FiltravelPorEstadoRegisto` (scope `filtrarPorEstadoRegisto()`); listagem aceita `?estado=`
  - `UtilizadorPolicy` — `viewAny`/`view` (auto-acesso)/`create`/`update`/`delete`; `TagCache::Utilizadores`
  - `UtilizadorController` (5 métodos) + `apiResource('utilizadores')` com `->withTrashed(['show','update','destroy'])`
  - **Eliminação Padrão B por pré-verificação** (hard delete sem referências; soft delete + revogação de tokens Sanctum quando referenciado); `restrictOnDelete` como salvaguarda; invariante de auto-eliminação (→ 422)
  - `CriarUtilizadorRequest`/`ActualizarUtilizadorRequest` não-final (mockáveis); `Password::min(8)->letters()->mixedCase()->numbers()->symbols()` + `confirmed`
  - Anonimização RGPD do ramo soft delete **adiada para Issue #73**
  - 674 testes totais, 100% cobertura, 100% type coverage, Larastan 9 zero erros

- **Issue #70** — CategoriaDocumento — SoftDeletes (Model Layer)
  - Migration `add_softdeletes_to_categorias_documento_table` — coluna `deleted_at` nullable em `categorias_documento`
  - Migration `update_fk_constraint_categoria_in_documentos` — `id_categoria` de `nullOnDelete` para `restrictOnDelete` (guarded para SQLite)
  - Model `CategoriaDocumento` — trait `SoftDeletes`; `delete()` faz soft delete; `@property-read ?Carbon $deleted_at`
  - Model `Documento` — `categoria()` usa `->withTrashed()` (integridade histórica de classificação)
  - `CategoriaDocumentoFactory` — state `inativa()` com `deleted_at = now()`
  - `CategoriaDocumentoResource` — campo `deleted_at` (5.º campo; ISO 8601 ou `null`)
  - Testes actualizados: `assertDatabaseMissing` → `assertSoftDeleted` em `EliminarCategoria*`; `DocumentoTest` substitui `nullOnDelete` por `withTrashed`; `CategoriaDocumentoTest` com secção `SoftDeletes`; feature tests de Criar/Ver/Actualizar actualizados com `deleted_at`
  - 609 testes totais, 100% cobertura, 100% type coverage, Larastan 9 zero erros

- **Issue #69** — Entidade — SoftDeletes (Model Layer)
  - Migration `add_softdeletes_to_entidades_table` — coluna `deleted_at` nullable em `entidades`
  - Migration `update_fk_constraints_entidades_in_documentos` — `id_fornecedor` e `id_cliente` de `nullOnDelete` para `restrictOnDelete` (guarded para SQLite)
  - Model `Entidade` — trait `SoftDeletes`; `delete()` faz soft delete; `@property-read ?Carbon $deleted_at`
  - Model `Documento` — `fornecedor()` e `cliente()` usam `->withTrashed()` (integridade histórica)
  - `EntidadeFactory` — state `inativa()` com `deleted_at = now()`
  - `EntidadeResource` — campo `deleted_at` (7.º campo; ISO 8601 ou `null`)
  - Testes actualizados: `assertDatabaseMissing` → `assertSoftDeleted` em `EliminarEntidade*`; `DocumentoTest` substitui `nullOnDelete` por `withTrashed`; `EntidadeTest` com secção `SoftDeletes`
  - 603 testes totais, 100% cobertura, 100% type coverage, Larastan 9 zero erros

- **Issue #57** — Documento — lógica (máquina de estados: Actions de transição + listagem + Regra* + Events)
  - **13 Actions**: 2 de criação (`RegistarDocumentoManualAction`, `ReceberUploadDocumentoAction`), 6 de pipeline programático (`MarcarAguardaEnvio`, `MarcarEnviado`, `MarcarAguardaResposta`, `TransicionarProcessado`, `MarcarErro`, `MarcarPerigoso`), 3 HTTP retidas (`Reprocessar`, `Corrigir`, `Eliminar`), 2 de leitura (`Listar`, `Ver`) + `DescarregarDocumentoAction`
  - **3 classes `Regra*`**: `RegraTransicaoEstado` (mapa central De→Para, match exaustivo sem `default`), `RegraMoverFicheiro` (cross-disk com compensação best-effort), `RegraNomearProcessado` (`yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}`)
  - **`ExecutorTransicaoDocumento`**: orquestrador partilhado interno à feature — elimina duplicação da mecânica de transição entre as 8 Actions simples
  - **7 DTOs** `final readonly`: `RegistarDocumentoManualDto` (ficheiro + campos de domínio), `ReceberUploadDocumentoDto`, `TransicionarProcessadoDocumentoDto`, `MarcarErroDocumentoDto`, `MarcarPerigosoDocumentoDto`, `ReprocessarDocumentoDto`, `CorrigirDocumentoDto` (só campos de domínio — sem campos de storage); `FicheiroDocumentoDto` (VO de vista)
  - **4 Events** `ShouldDispatchAfterCommit`: `DocumentoProcessado`, `DocumentoMarcadoErro`, `DocumentoMarcadoPerigoso`, `DocumentoReprocessado` — sem Listeners nesta issue
  - **2 enums novos**: `ModoReprocessamento` (`Modelo`, `Ferramenta`); `CampoOrdenacaoDocumentos` (`DataDocumento`, `CriadoEm`)
  - **`TransicaoInvalidaException`** mapeada para 422 em `bootstrap/app.php`
  - **`TagCache::Documentos`** adicionado à infra de cache
  - **Camada HTTP completa**: `DocumentoController` (zero lógica), 8 FormRequests com `authorize()` + `messages()` PT, `DocumentoResource` (com `whenLoaded('historico')`), `EtapaDocumentoResource`, 8 endpoints (Listar, Criar, Upload, Ver, Corrigir, Reprocessar, Eliminar, Descarregar)
  - **Decisões notáveis**: ficheiro movido antes da transação (compensação best-effort em falha); DTOs #45 (`CriarDocumentoManualDto`, `ActualizarDocumentoDto`) removidos e substituídos por DTOs sem campos de storage; `DescarregarDocumentoAction` adicionada (não estava na spec)
  - `docs/system_spec/01-features/documento.md` criado; `02-shared/{regras-negocio,estados,enums,http}.md`, `04-infra/{queue-jobs,cache}.md`, `05-routes/documento.md` actualizados; `00-index.md` actualizado; `openapi.yaml` actualizado
  - 527 testes totais, 100% cobertura, 100% type coverage, Larastan 9 zero erros

- **Issue #56** — EtapaDocumento — Camada de Modelo (histórico append-only de estados do documento)
  - Migration `etapas_documento`: UUID PK; FK `id_documento` → `documentos` `cascadeOnDelete()`; `estado string(50)` com índice; `motivo text nullable`; `id_utilizador bigint FK nullable` → `users` `nullOnDelete()`; só `created_at` (sem `updated_at` — append-only)
  - Model `EtapaDocumento` — `HasUuids`; `const UPDATED_AT = null` (append-only); cast `estado → EstadoDocumento`; relações `documento()` e `utilizador()` `BelongsTo`; `@property-read` completo; **sem `RegistaActividade`** (tabela *é* o histórico de domínio)
  - Relação `Documento->historico()` — `hasMany(EtapaDocumento::class, 'id_documento')->orderBy('created_at')` (linha temporal ascendente)
  - `EtapaDocumentoFactory` — base `Pendente` (`id_utilizador = null`, `motivo = null`); states: `processado()`, `erro()` (com `motivo`), `perigoso()` (com `motivo`), `manual()` (`id_utilizador` via `User::factory()`)
  - Testes unitários: `EtapaDocumentoTest` (model, append-only, casts, relações, `cascadeOnDelete`/`nullOnDelete`, factory states com dataset); `DocumentoTest` ampliado com bloco `historico` ordenado
  - Decisão documentada: `id_utilizador` é `bigint → users` (não UUID/`utilizadores`) — schema real; `foreignUuid` falharia em runtime
  - 420 testes totais, 100% cobertura, 100% type coverage, Larastan 9 zero erros

- **Issue #45** — Documento — Camada de Modelo (migration + enum + state objects + model + factory + policy + DTOs + resource + testes)
  - Migration `documentos`: UUID PK, `status` string+índice, 3 FKs nullable `nullOnDelete()` (→ `entidades` × 2, → `categorias_documento`), `valor decimal(15,2)`, `data_documento date`+índice, `nome_ficheiro_original`, `disco_storage`, `nome_ficheiro_storage`, `hash_sha256` único
  - Enum `EstadoDocumento` (PT-PT) — 7 casos: `Pendente`, `AguardaEnvio`, `Enviado`, `AguardaResposta`, `Processado`, `Erro`, `Perigoso` (substitui placeholder EN `DocumentStatus`)
  - Interface `ContratoEstadoDocumento` — 4 getters comuns a todos os estados (`estado()`, `id()`, `discoStorage()`, `nomeFicheiroStorage()`)
  - 7 state objects `final readonly` (`DocumentoPendente`, `DocumentoAguardaEnvio`, `DocumentoEnviado`, `DocumentoAguardaResposta`, `DocumentoProcessado`, `DocumentoErro`, `DocumentoPerigoso`)
  - Model `Documento` — `HasUuids`; atributos PHP `#[Table]` `#[Fillable]` `#[UsePolicy]`; casts (`status` → enum, `valor` → `decimal:2` (string), `data_documento` → Carbon); `estado()` com `match` exaustivo (sem `default`); 3 relações `BelongsTo`; 5 scopes (`whereEstado`, `whereProcessado`, `wherePendente`, `wherePerigoso`, `whereErro`); `RegistaActividade` excluindo campos sensíveis (`hash_sha256`, `disco_storage`, `nome_ficheiro_storage`)
  - `DocumentoFactory` — base = `processado()` (todos os campos); 7 states com mapeamento correcto estado→disco (`entrada`/`enviado`/`processado`/`erro`/`perigoso`)
  - `DocumentoPolicy` — stub com 5 métodos `true`; `final class`; assinaturas prontas para `hasPermissionTo()`
  - `CriarDocumentoManualDto` + `ActualizarDocumentoDto` — `final readonly`; invariantes: `valor >= 0`, `hashSha256` = 64 chars, strings obrigatórias não-vazias; sem `fromRequest()` (pertence à #57)
  - `DocumentoResource` — serialização JSON; `valor` convertido para `float`; relações via `whenLoaded()`; omite `disco_storage`/`nome_ficheiro_storage`
  - 5 discos de storage PT em `config/filesystems.php`: `entrada`, `enviado`, `processado`, `erro`, `perigoso`
  - Testes unitários: `DocumentoTest`, `EstadoDocumentoStatesTest`, `DocumentoPolicyTest`, `CriarDocumentoManualDtoTest`, `ActualizarDocumentoDtoTest`, `DocumentoResourceTest`
  - 399 testes totais, 100% cobertura, 100% type coverage, Larastan 9 zero erros

- **Issue #54** — Audit Trail com spatie/laravel-activitylog
  - `spatie/laravel-activitylog ^4.0` instalado; tabela `activity_log` migrada antes dos seeds de roles
  - `app/Models/Concerns/RegistaActividade` — trait que centraliza a política de audit trail (`logFillable + logOnlyDirty + dontSubmitEmptyLogs`) com hook `atributosExcluidosDaActividade()` para campos sensíveis
  - `CategoriaDocumento` e `Entidade` — adicionam `RegistaActividade`; `Entidade` exclui `nif` (dado fiscal — RGPD)
  - `app/Observers/RoleObserver` — audita `Spatie\Permission\Models\Role` (modelo de terceiro) via Observer registado em `AppServiceProvider`; sem alterações a `config/permission.php`
  - `subject_id` em `char(36)` (em vez de `bigint`) para acomodar sujeitos com UUID e bigint em simultâneo
  - `causer` associado automaticamente ao `Auth::user()` pelo pacote — sem configuração nas Actions
  - Atomicidade garantida: eventos Eloquent disparam dentro da `DB::transaction()` das Actions; rollback reverte o registo de actividade
  - Testes Unit (`tests/Unit/Features/AuditTrail/`): 3 ficheiros — `created`/`updated`/`deleted`, no-op (logOnlyDirty), rollback e exclusão de `nif`
  - Assertions `Activity::count()` adicionadas nos testes HTTP de escrita existentes (CategoriaDocumento, Entidade, Role) — criar/actualizar/eliminar/403
  - `docs/system_spec/04-infra/audit-trail.md` criado; `docs/system_spec/03-models/role.md` criado
  - 315 testes totais, 100% cobertura, PHPStan nível 9 sem erros

- **Issue #37** — Logging estruturado — Actions, autenticação e contexto de request
  - `app/Http/Middleware/InjectarContextoLog` — gera `trace_id` UUID por request via `Context::add()`; registado no grupo `api`; propaga automaticamente para Jobs (Laravel Context dehydrate/hydrate)
  - `app/Features/Auth/Login/LoginDto` — Value Object `final readonly` com `email`, `password`, `ip`, `agente`; `fromRequest()` extrai contexto HTTP (IP de `$request->ip()`, nunca do body)
  - `LoginAction` — assinatura alterada para `handle(LoginDto $dados)`; eventos `auth.login.tentativa` (info), `auth.login.sucesso` (info), `auth.login.falhou` (warning); password nunca logada
  - 7 Actions de escrita — padrão `<dominio>.<operacao>.inicio` / `.fim` com `id_utilizador`; `.fim` só registado após commit da transação
  - `bootstrap/app.php` — `$exceptions->report()` com `Log::error()` global para todas as excepções não tratadas
  - `.env.example` — `LOG_DAILY_DAYS=14` documentado; canal `daily` configurado em `config/logging.php` para produção
  - 4 ficheiros de teste novos (`InjectarContextoLogTest`, `LoginDtoTest`, `LoginActionLogTest`, `CriarCategoriaLogTest`) + actualização de `LoginActionTest`
  - `docs/system_spec/04-infra/logging.md` — catálogo completo de eventos, padrão de logging e configuração
  - 299 testes totais, 100% cobertura, PHPStan nível 9 sem erros

- **Issue #38** — Cache Redis — listagens e queries frequentes com invalidação por tags
  - Infra partilhada `app/Shared/Cache/`: `TagCache` (enum domínio), `TagOperacao` (enum operação), `TtlCache` (enum duração: `Curta=30s`, `Media=300s`, `Longa=3600s`, `Alargada=86400s`), `CacheServico` (serviço final injectável com `criarChave()`, `lembrar()`, `invalidarCache()`)
  - `ListarEntidadesAction` e `VerEntidadeAction` — cache com `TagCache::Entidades`; 4 Actions de escrita invalidam em `DB::transaction()`
  - `ListarCategoriasAction` e `VerCategoriaAction` — cache com `TagCache::CategoriasDocumento`; 3 Actions de escrita invalidam em `DB::transaction()`
  - `config/cache.php` — `serializable_classes` com whitelist explícita (`Entidade`, `CategoriaDocumento`, `CursorPaginator`, `Collection`, `EloquentCollection`); default `redis`
  - `PERMISSION_CACHE_STORE=array` em `phpunit.xml` — isola cache Spatie por worker em testes paralelos
  - `predis/predis` adicionado ao `composer.json`; `REDIS_CLIENT=predis` em `.env`
  - 289 testes totais, 100% cobertura, PHPStan nível 9 sem erros

- **Issue #50** — Gestão de roles e atribuição de role a utilizadores
  - Feature slice `Role`: CRUD completo (`ListarRoles`, `VerRole`, `CriarRole`, `ActualizarRole`, `EliminarRole`) com `RoleResource`, `CampoOrdenacaoRoles`, DTOs e cursor pagination
  - Feature slice `Utilizador`: `AtribuirRole` — substitui role via `syncRoles()`
  - `RolePolicy` + `UtilizadorPolicy` — autorização por permission granular; `RolePolicy` registada via `Gate::policy()` no `AppServiceProvider`; `UtilizadorPolicy` via `#[UsePolicy]` no modelo `User`
  - 5 novas permissions (`roles.ver`, `roles.criar`, `roles.actualizar`, `roles.eliminar`, `utilizadores.atribuir-role`) criadas via data migration e atribuídas ao role `admin`
  - Invariante de domínio: utilizador não pode alterar o próprio role (`DomainException` → 422)
  - Roles de sistema (`admin`, `utilizador`) protegidos contra eliminação (`DomainException` → 422)
  - `DomainException` mapeada para 422 em `bootstrap/app.php`
  - 277 testes totais, 100% cobertura, PHPStan nível 9 sem erros

### Changed
- **Issue #48** — Refactor: helpers globais de autenticação e `describe()` por role nos testes
  - `tests/Pest.php` — `beforeEach` global com `forgetCachedPermissions()` (removido dos 22 ficheiros individuais) + 4 helper functions: `criarAdmin()`, `criarUtilizador()`, `criarEAutenticarAdmin()`, `criarEAutenticarUtilizador()`
  - 11 Unit Action tests (Entidade + CategoriaDocumento) reestruturados com `describe('como admin')` e `describe('sem permissão de escrita/leitura')` — elimina override inline que desperdiçava o utilizador do `beforeEach`
  - 11 Feature tests simplificados: `describe('autenticado') { beforeEach }` usa `criarEAutenticarAdmin()`; testes 403 usam `criarEAutenticarUtilizador()` (1 linha em vez de 3)
  - Imports `PermissionRegistrar`, `Sanctum`, `User` removidos onde dispensáveis
  - `docs/system_spec/07-testing.md` — nova secção "Helpers globais de autenticação" com tabela dos 4 helpers e padrões `describe()` para Unit e Feature tests
  - Redução líquida: ~185 linhas de boilerplate eliminadas; 229 testes, 100% cobertura, PHPStan nível 9 sem erros

### Added
- **Issue #36** — Autorização por roles/permissions (Spatie Laravel Permission + Policies)
  - `spatie/laravel-permission ^8.0` instalado; guard `web` (único guard configurado — Sanctum autentica via middleware, não regista guard separado)
  - Data migration `seed_roles_and_permissions` — cria roles (`admin`, `utilizador`) e 8 permissions (`entidades.*`, `categorias-documento.*`) automaticamente com `php artisan migrate` em todos os ambientes, incluindo produção
  - `HasRoles` adicionado ao model `User`; `@property-read Collection<int, Role> $roles` e `@property-read Collection<int, Permission> $permissions` documentados
  - `EntidadePolicy` e `CategoriaDocumentoPolicy`: stubs `return true` substituídos por `hasPermissionTo()` real — leitura para `utilizador`; escrita exclusiva para `admin`
  - `RolesPermissionsSeeder` (desenvolvimento): cria `admin@findocprocessor.test` com role `admin` e token Sanctum `dev-token`
  - **Testes dupla camada:** 7 cenários 403 para `utilizador` em operações de escrita (HTTP); 4 cenários 200 para `utilizador` em leitura; 11 cenários `AuthorizationException` nas Actions (invocação directa sem permissão)
  - 22 ficheiros de testes existentes actualizados: `admin` role + `PermissionRegistrar::forgetCachedPermissions()` no `beforeEach`
  - System spec: `03-models/user.md` actualizado; `04-infra/autorizacao.md` criado; `00-index.md` actualizado
  - 229 testes, 100% cobertura, PHPStan nível 9 sem erros

- **Issue #35** — Autenticação via Laravel Sanctum (API tokens Bearer)
  - `laravel/sanctum v4.3.2` instalado via `php artisan install:api`; migration `personal_access_tokens` criada; `SANCTUM_TOKEN_EXPIRATION=525600` (1 ano) no `.env.example`
  - `HasApiTokens` adicionado ao model `User`; `@property-read Collection<int, PersonalAccessToken> $tokens` documentado
  - Feature slice `Auth`: `LoginAction` (emite token), `LogoutAction` (revoga token actual), `CriarTokenAction` (cria token adicional); `AuthController` com 3 endpoints
  - Rotas `POST /auth/login` (pública), `POST /auth/logout` e `POST /auth/tokens` (protegidas); todas as rotas existentes movidas para grupo `auth:sanctum`
  - **Breaking change:** `GET/POST /api/categorias-documento`, `GET/POST /api/entidades` e sub-rotas passam a exigir `Authorization: Bearer <token>` — pedidos sem token recebem 401
  - Policies `CategoriaDocumentoPolicy` e `EntidadePolicy`: `?User` → `User` (guests não chegam às policies com `auth:sanctum`)
  - `ApiResponse::devolverSucesso()` estendido para aceitar `JsonResource|array<string, mixed>`
  - Padrão dual de testes Auth: 3 Unit tests (Actions) + 5 Feature tests (HTTP + regressão)
  - 11 Feature tests existentes actualizados: `Sanctum::actingAs()` + testes 401 por endpoint
  - 12 Unit tests existentes actualizados: `beforeEach(actingAs())` — necessário após policies tornarem-se não-nullable
  - `openapi.yaml` criado com `bearerAuth` (OpenAPI 3.1.0), segurança global, `POST /auth/login` pública e todas as 14 rotas documentadas
  - Índice parcial MySQL removido da migration `entidades` (incompatível com MySQL/MariaDB); garantia mantida na Action layer
  - 187 testes, 100% cobertura, PHPStan 0 erros

- **Issue #41** — `CategoriaDocumento`: `ListarCategoriasActionTest` — padrão dual completo
  - Criado `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php` (3 testes: lista vazia, ordenação ascendente, `per_page` com cursor)
  - Padrão dual unit+feature agora completo para todas as 5 Actions de `CategoriaDocumento`

- **Issue #40** — `Entidade`: feature slice completo (Actions + Controller + FormRequests + Testes)
  - CRUD completo via API REST: `GET/POST /api/entidades`, `GET/PUT/DELETE /api/entidades/{id}`, `PATCH /api/entidades/{id}/empresa-mae`
  - `CriarEntidadeAction`, `VerEntidadeAction`, `ActualizarEntidadeAction`, `EliminarEntidadeAction`, `ListarEntidadesAction` — com autorização dupla (FormRequest + Action) e `DB::transaction()` nas actions de escrita
  - `ConverterEmEmpresaMaeAction` — converte entidade em Empresa Mãe, forçando os 3 flags (`e_empresa_aplicacao`, `e_cliente`, `e_fornecedor`); endpoint dedicado `PATCH /empresa-mae`
  - `RemoverMarcacaoEmpresaMaeAction` (action interna) + `RegraUnicidadeEmpresaMae` (classe de domínio) — encapsulam a regra de unicidade da Empresa Mãe; invocados dentro da transação do caller, sem autorização própria
  - Trait `ComFlagsEfectivosEmpresaMae` nos DTOs — `eClienteEfectivo()` / `eFornecedorEfectivo()` garantem a invariante "Empresa Mãe implica cliente + fornecedor"
  - `fromRequest()` adicionado a `CriarEntidadeDto` e `ActualizarEntidadeDto` com array shape para Larastan nível 9
  - `CampoOrdenacaoEntidades` enum para ordenação da listagem; cursor pagination (`cursorPaginate`)
  - 6 FormRequests (um por operação); `EntidadeController` final sem lógica
  - Testes: padrão dual obrigatório — 11 ficheiros `tests/Unit/Features/Entidade/` (invocação directa de Actions) + 6 ficheiros `tests/Feature/Features/Entidade/` (HTTP); 170 testes, 100% cobertura
  - `docs/conventions/tests-dual-pattern.md` criado (referência detalhada com exemplos de rollback, estrutura de ficheiros e ArchTest); `CLAUDE.md` actualizado com regras resumidas do padrão dual

### Changed
- **Issue #34** — Transações de BD nas Actions de escrita (`CategoriaDocumento`)
  - `CriarCategoriaAction`, `ActualizarCategoriaAction`, `EliminarCategoriaAction` envolvem a persistência em `DB::transaction()` — `Gate::authorize()` fica fora, rollback e re-lançamento de `\Throwable` são automáticos
  - Padrão documentado em `CLAUDE.md` (secção "Padrões obrigatórios") e `docs/system_spec/04-infra.md`
  - Novos testes de rollback: `CriarCategoriaActionTest` (novo ficheiro), `ActualizarCategoriaActionTest` e `EliminarCategoriaActionTest` (adições) — usam model events (`created`/`saved`/`deleting`) para verificar rollback em falha a meio

### Added
- **Issue #32** — `Entidade`: persistence layer (DTOs + Resource)
  - `CriarEntidadeDto` (`final readonly`) — construtor valida `nome`/`nif` não-vazios; booleans sem validação; sem `fromRequest()` (adicionado na issue de lógica)
  - `ActualizarEntidadeDto` (`final readonly`) — estrutura idêntica; update completo (PUT semântico), todos os campos obrigatórios
  - `EntidadeResource` — serializa 6 campos (`id`, `nome`, `nif`, `e_cliente`, `e_fornecedor`, `e_empresa_aplicacao`); booleans como `bool`; timestamps omitidos
  - 13 testes unitários: 5 por DTO (4 invariantes + happy path) + 3 no Resource (campos, sem timestamps, tipos bool)

### Changed
- **Issue #30** — `CategoriaDocumento`: forçar update completo (PUT semântico) — remover actualizações parciais
  - `ActualizarCategoriaRequest`: `sometimes` → `required` nos 3 campos; mensagens `.required` adicionadas (`nome`, `slug`, `tipo_movimento`)
  - `ActualizarCategoriaDto`: propriedades `?string`/`?TipoMovimento` → não-nullable; null guards condicionais removidos do construtor; array shape `{nome?: ...}` → `{nome: ...}`; estrutura agora idêntica ao `CriarCategoriaDto`
  - `ActualizarCategoriaAction`: `array_filter(..., fn => $valor !== null)` removido — `fill()` directo com os 3 campos
  - Testes actualizados: helpers `payloadCompleto()`/`payloadActualizar()`; testes de actualização parcial substituídos por testes de `required` por campo; `ActionTest` valida os 3 campos no resultado

### Added
- **Issue #27** — `Entidade`: camada de modelo completa
  - Migration `entidades` com UUID PK, booleanos indexados (`e_cliente`, `e_fornecedor`, `e_empresa_aplicacao`) e índice parcial único MySQL (`unica_empresa_mae_idx WHERE e_empresa_aplicacao = 1`) protegido por guard de driver
  - Model `Entidade` com `HasUuids`, `#[Fillable]`, `#[Table]`, `#[UsePolicy]`, casts `'boolean'` nas 3 flags e 3 scopes (`whereCliente`, `whereFornecedor`, `whereEmpresaAplicacao`) com `Builder<Entidade>` nos PHPDocs (Larastan nível 9)
  - Factory com 4 states: `cliente()`, `fornecedor()`, `clienteEFornecedor()`, `empresaAplicacao()` — empresa mãe é obrigatoriamente cliente e fornecedor
  - `EntidadePolicy` placeholder: `?User $utilizador`, todos os métodos retornam `true` (autorização real em issue futura de lógica)
  - 17 testes: model (uuid, fillable, timestamps, casts, scopes, factory states) + policy (utilizador autenticado e guest — ambos autorizados nesta fase)

### Changed
- **Issue #28** — DTOs `CriarCategoriaDto` e `ActualizarCategoriaDto`: adoptar padrão Value Object
  - Construtor valida invariantes estruturais (`nome !== ''`, `slug !== ''`) com `@throws \InvalidArgumentException`
  - `fromRequest()` simplificado — só mapeia dados; sem guards `is_string()` redundantes com o array shape PHPDoc
  - CLAUDE.md actualizado com o novo padrão obrigatório (tabela de responsabilidades por camada)

### Added
- **Issue #25** — `CategoriaDocumento`: Policy de autorização CRUD
  - `CategoriaDocumentoPolicy` em `app/Policies/` — 5 métodos (`viewAny`, `view`, `create`, `update`, `delete`), todos `return true` com `?User $utilizador` nullable (guest support); auto-descoberta por convenção de nome
  - `VerCategoriaRequest` e `EliminarCategoriaRequest` — novos FormRequests mínimos (só `authorize()`, sem `rules()`); injectados no Controller em `show` e `destroy`
  - 3 FormRequests existentes actualizados: `return true` substituído por `Gate::authorize()` com a ability correcta
  - 5 Actions actualizadas com `Gate::authorize()` em `handle()` e `@throws AuthorizationException` no PHPDoc
  - Dupla verificação: FormRequest (HTTP) + Action (lógica) — defence in depth para invocações fora do contexto HTTP
  - 5 testes de guest adicionados (um por endpoint) — todos 2xx nesta fase
  - `rector.php`: `withSkip([RemoveUnusedPublicMethodParameterRector::class => ['app/Policies']])` — parâmetros `?User` são contrato do framework, não dead code

### Changed
- **Issue #12** — `ListarCategoriasTest`: `assertJsonStructure` adicionado a 4 testes de listagem
  - `'devolve lista vazia'` e `'cursor além do fim'`: envelope validado sem items (`'data'`, `links`, `meta`)
  - `'respeita per_page'` e `'navega via cursor'` (2 páginas): envelope + items `['id', 'nome', 'slug', 'tipo_movimento']`
  - CA-02 adaptado para cursor pagination: campos `next_cursor/prev_cursor` em vez de `total/current_page/last_page`
- **Issue #22** — `CategoriaDocumento`: correcção de nomenclatura (camelCase, nomes contextuais, consistência no Controller)
  - `CriarCategoriaDto` / `ActualizarCategoriaDto`: propriedade `$tipo_movimento` → `$tipoMovimento`; variável local `$validated` → `$dadosValidados`
  - `ActualizarCategoriaAction`: variável `$campos` → `$camposParaActualizar`; acesso `$dados->tipo_movimento` → `$dados->tipoMovimento`
  - `CategoriaDocumentoController`: `$validated` → `$parametrosValidados` em `index()`; parâmetro `$request` → `$pedido` em `store()` e `update()`
  - Testes actualizados: named arg `tipo_movimento:` → `tipoMovimento:` em `ActualizarCategoriaActionTest`
  - Chaves `'tipo_movimento'` nos arrays Eloquent (`create()` / `fill()`) mantêm-se snake_case (coluna BD)
- **Issue #17** — Auditoria de tipagem: `@throws` e `@var` em Actions restantes
  - `EliminarCategoriaAction::handle()`: `@throws ModelNotFoundException<CategoriaDocumento>` + `@var CategoriaDocumento $categoria` (consistência com `ActualizarCategoriaAction`)
  - `VerCategoriaAction::handle()`: `@throws ModelNotFoundException<CategoriaDocumento>` (retorno directo — sem variável intermédia, sem `@var`)
  - Regra B aplicada a `findOrFail()` mesmo sem `throw` explícito — propaga `ModelNotFoundException` para o caller
- **Issue #15** — `ActualizarCategoriaAction`: substituir `fresh()` por `refresh()`
  - `return $categoria->fresh() ?? $categoria` → `$categoria->refresh(); return $categoria` (re-hidrata instância existente em vez de criar nova)
  - `@throws ModelNotFoundException` adicionado (Regra B — `refresh()` usa `findOrFail()` internamente)
  - `@var CategoriaDocumento $categoria` adicionado para resolução de tipo no IDE (Larastan já inferia correctamente)
- **Issue #16** — `CategoriaDocumento` DTOs: anotações PHPDoc de tipagem (`@var` array shape + `@throws`)
  - `CriarCategoriaDto.fromRequest()`: `@var array{nome: string, slug: string, tipo_movimento: string}` + `@throws \UnexpectedValueException`
  - `ActualizarCategoriaDto.fromRequest()`: `@var array{nome?: string, slug?: string, tipo_movimento?: string}` + `@throws \UnexpectedValueException`
  - `phpstan.neon`: `treatPhpDocTypesAsCertain: false` — aceita padrão simultâneo anotação estática + runtime guard sem falsos positivos do Larastan nível 9
- **Issue #10** — `CLAUDE.md`: Repository pattern qualificado com critérios objectivos
  - Regra "Repositório entre Action e Eloquent Model" substituída por regra condicional: obrigatório em queries complexas (joins, aggregates, raw SQL, partilha entre ≥ 2 Actions); dispensável em CRUD simples (≤ 1 query Eloquent por `handle()`)
  - Secção "O que NÃO fazer" alinhada com a nova regra — excepção CRUD simples documentada com remissão cruzada

### Added
- **Issue #9** — `CategoriaDocumento`: cursor pagination na listagem (`GET /api/categorias-documento`)
  - `CampoOrdenacaoCategorias` — enum backed string com `case Nome = 'nome'`; extensível para campos futuros
  - `DirecaoOrdenacao` — enum partilhado em `App\Shared\Enums` (`Asc`/`Desc`); reutilizável em todas as listagens
  - `ListarCategoriasRequest` — valida `per_page` (1–100, default 15), `sort` (enum values), `direction` (asc/desc), `cursor` (opaco); mensagens em PT
  - `ListarCategoriasAction::handle()` — assinatura alargada com `CampoOrdenacaoCategorias` e `DirecaoOrdenacao`; body: `::all()` → `::orderBy(...)->cursorPaginate()`
  - `ApiResponse::devolverPaginado()` — novo método; delega em `$coleccao->response()` para resolução automática de `links` e `meta`
  - Resposta inclui `links.next/prev` e `meta.next_cursor/prev_cursor`; sem `meta.total` (trade-off keyset)
  - 7 cenários de teste: lista vazia, estrutura, `per_page` custom, navegação via cursor, `per_page` > 100 (422), `sort` inválido (422), cursor além do fim (200 com `data=[]`)
  - **Breaking change:** formato de resposta da listagem alterado (aceite e declarado na issue)
- **Issue #5** — `CategoriaDocumento`: camada de lógica (Actions + Controller + DTOs)
  - 5 Actions CRUD: `ListarCategoriasAction`, `CriarCategoriaAction`, `VerCategoriaAction`, `ActualizarCategoriaAction`, `EliminarCategoriaAction`
  - 2 DTOs `final readonly`: `CriarCategoriaDto`, `ActualizarCategoriaDto` com `fromRequest()` + guards `is_string()` (Larastan nível 9)
  - `CategoriaDocumentoController` sem lógica — dispatch puro com Route Model Binding e injecção de Actions
  - `Route::apiResource('categorias-documento', ...)` → 5 endpoints REST (`GET`, `POST`, `GET/{id}`, `PUT/{id}`, `DELETE/{id}`)
  - Actions aceitam `CategoriaDocumento|string` — compatíveis com RMB (HTTP) e testes unitários (UUID directo)
  - Fix `ActualizarCategoriaRequest`: parâmetro de rota corrigido para `categorias_documento` (gerado pelo `apiResource`)
  - 62 testes (5 feature + unit por Action e DTO), 188 assertions, 100% coverage
- **Issue #6** — Envelope universal de resposta JSON: `ApiResponse` + Problem Details RFC 7807
  - `ApiResponse` em `App\Shared\Http` — factory estática com `devolverSucesso`, `devolverCriado`, `devolverVazio`, `devolverColeccao`
  - Exception handler centralizado em `bootstrap/app.php` — mapeia 5 classes de excepção para Problem Details (422/404/403/401/500)
  - Stack traces nunca expostos; mensagens de `detail` em português de Portugal
  - 9 testes de feature: `ApiResponseTest` (4) + `ExceptionHandlerTest` (5)
- **Issue #3** — `CategoriaDocumento`: camada de API (Resource + FormRequests)
  - `CategoriaDocumentoResource` em `App\Features\CategoriaDocumento` — expõe `id`, `nome`, `slug`, `tipo_movimento` (string)
  - `CriarCategoriaRequest` com validação completa (`required`, `Rule::unique`, `Rule::in`) e mensagens em português
  - `ActualizarCategoriaRequest` com campos `sometimes` e `Rule::unique()->ignore($uuid)` para actualizações parciais
  - 16 testes unitários: Resource, CriarRequest (incl. unicidade com BD), ActualizarRequest (incl. ignore de slug próprio)
  - Fix `ArchTest`: `ignoring('App\Features')` no preset `laravel` — Vertical Slice coloca FormRequests/Resources dentro da slice
  - Fix `composer.json`: `--memory-limit=512M` no `test:types` (PHPStan/Larastan nível 9)
- **Issue #1** — `CategoriaDocumento`: camada de modelo completa
  - Enum `TipoMovimento` (`Debito`, `Credito`, `Neutro`) em `App\Shared\Enums`
  - Migration `categorias_documento` com UUID PK, índice em `nome`, único em `slug`
  - Model `CategoriaDocumento` com `HasUuids`, `#[Fillable]`, `#[Table]`, cast para `TipoMovimento`
  - Factory com `definition()` aleatório e states `comMovimentoDebito/Credito/Neutro`
  - 11 testes unitários: model, factory states, constraints BD
  - Fix `ArchTest`: `.ignoring('App\Shared\Enums')` no preset `laravel`
- Estrutura inicial do projecto (scaffolding)

---

_Actualizado automaticamente pela Fase 3 (documenta-issue) após cada Issue._
