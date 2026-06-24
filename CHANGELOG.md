# Changelog — FinDocProcessor Backend Laravel

Formato: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)

---

## [Unreleased]

### Added
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
