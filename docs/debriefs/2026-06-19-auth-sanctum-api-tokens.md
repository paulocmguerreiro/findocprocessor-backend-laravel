# Debrief — Issue #35: feat(auth): autenticação via Laravel Sanctum

**Data:** 2026-06-19
**Issue:** #35
**Slug:** `auth-sanctum-api-tokens`
**Branch:** `feat/auth-sanctum-api-tokens`
**Estado:** concluído

---

## O que foi implementado

Instalação do Laravel Sanctum v4.3.2 e criação da feature slice `Auth` com três Actions
(Login, Logout, CriarToken). Todas as rotas existentes passaram a exigir `Authorization: Bearer <token>`.
Policies corrigidas para `User` não-nullable. Padrão dual de testes criado para Auth e todos os
testes existentes adaptados ao novo contexto de autenticação.

### Ficheiros criados

| Ficheiro | Tipo |
|---|---|
| `config/sanctum.php` | Configuração (publicado por `install:api`) |
| `database/migrations/*_create_personal_access_tokens_table.php` | Migration |
| `app/Features/Auth/Login/LoginRequest.php` | FormRequest |
| `app/Features/Auth/Login/LoginAction.php` | Action |
| `app/Features/Auth/Logout/LogoutAction.php` | Action |
| `app/Features/Auth/CriarToken/CriarTokenRequest.php` | FormRequest |
| `app/Features/Auth/CriarToken/CriarTokenAction.php` | Action |
| `app/Features/Auth/AuthController.php` | Controller |
| `tests/Unit/Features/Auth/LoginActionTest.php` | Teste unitário |
| `tests/Unit/Features/Auth/LogoutActionTest.php` | Teste unitário |
| `tests/Unit/Features/Auth/CriarTokenActionTest.php` | Teste unitário |
| `tests/Feature/Features/Auth/LoginTest.php` | Teste de feature |
| `tests/Feature/Features/Auth/LogoutTest.php` | Teste de feature |
| `tests/Feature/Features/Auth/CriarTokenTest.php` | Teste de feature |
| `tests/Feature/Features/Auth/AcessoProtegidoTest.php` | Teste de regressão |
| `openapi.yaml` | Documentação OpenAPI 3.1.0 |
| `docs/system_spec/01-features/auth.md` | System spec |
| `docs/system_spec/05-routes/auth.md` | System spec |
| `docs/system_spec/03-models/user.md` | System spec |

### Ficheiros modificados

| Ficheiro | Modificação |
|---|---|
| `composer.json` / `composer.lock` | `laravel/sanctum v4.3.2` adicionado |
| `.env.example` | `SANCTUM_TOKEN_EXPIRATION=525600` |
| `app/Models/User.php` | `HasApiTokens` + `@property-read $tokens` |
| `routes/api.php` | Grupo `auth:sanctum` + novas rotas Auth |
| `app/Policies/CategoriaDocumentoPolicy.php` | `?User` → `User` (5 métodos) |
| `app/Policies/EntidadePolicy.php` | `?User` → `User` (5 métodos) |
| `app/Shared/Http/ApiResponse.php` | `devolverSucesso()` aceita `JsonResource\|array` |
| `database/migrations/*_create_entidades_table.php` | Removido índice parcial incompatível com MySQL |
| 11 ficheiros Feature tests existentes | `Sanctum::actingAs()` + testes 401 |
| 12 ficheiros Unit tests existentes | `beforeEach(actingAs())` — policies agora exigem `User` |
| `tests/Unit/Policies/EntidadePolicyTest.php` | Removido `describe('Guest')` (5 testes obsoletos) |
| `docs/system_spec/00-index.md` | Auth, User, rotas Auth adicionados |
| `docs/system_spec/03-models/entidade.md` | Removida secção de índice parcial |
| `docs/system_spec/03-models/00-convencoes-models.md` | Removido parágrafo sobre índices parciais |

---

## Decisões tomadas

### 1. Resources eliminados — `ApiResponse::devolverSucesso` aceita `array`

O plano previa `LoginResource` e `CriarTokenResource` (T3c). Durante a implementação,
o Larastan nível 9 rejeitou qualquer operação sobre `$this->resource` dentro de um `JsonResource`
porque o tipo é `mixed` e não pode ser narrowed sem workarounds. Criar dois Resources para devolver
uma única string não justificava a complexidade.

Decisão: eliminar os Resources; o Controller devolve directamente
`ApiResponse::devolverSucesso(['token' => $token])`. Para suportar este padrão,
`ApiResponse::devolverSucesso()` foi estendida para aceitar `JsonResource|array<string, mixed>`
em vez de só `JsonResource`. Coerente com o padrão existente e sem duplicação (`devolverDados`
foi considerado e rejeitado por ser cópia de `devolverSucesso`).

### 2. Índice parcial removido da migration de `entidades`

A migration `create_entidades_table` tinha um `DB::statement('CREATE UNIQUE INDEX ... WHERE ...')`
para garantir unicidade de `e_empresa_aplicacao = true`. Este índice é suportado em SQLite e
PostgreSQL mas **não em MySQL/MariaDB** (ausência de suporte a partial indexes com WHERE clause).
Como o projecto usa SQLite em dev e MySQL em prod, a migration falhava em prod.

Decisão: remover o índice completamente e manter a garantia de unicidade exclusivamente na
Action layer (`RegraUnicidadeEmpresaMae`). A regra já existia e é suficiente. System spec
actualizado para não referenciar índices parciais.

### 3. Auth Actions sem `Gate::authorize()`

Desvio intencional ao padrão "autorização dupla camada" do CLAUDE.md:
- `LoginAction` — acção pública; utilizador ainda não autenticado.
- `LogoutAction` e `CriarTokenAction` — utilizador revoga/cria os seus próprios tokens;
  o middleware `auth:sanctum` garante autenticação; não existe Policy de `PersonalAccessToken`.

Documentado como excepção em `docs/system_spec/01-features/auth.md`.

### 4. `LogoutTest` usa token real (não TransientToken)

`Sanctum::actingAs()` cria um `TransientToken` — não persiste na BD. Um teste que verifica
`$utilizador->tokens()->count() === 0` após logout verificaria uma contagem que já era zero antes.

Solução: `LogoutTest` (Feature) cria um token real com `createToken()`, autentica via
`withToken($plainTextToken)` e só então verifica a eliminação. `LogoutActionTest` (Unit)
usa `withAccessToken($accessToken)` para definir o `currentAccessToken` sem HTTP.

### 5. `actingAs()` em 12 ficheiros Unit tests além do planeado

O plano (T6) previa actualizar os Feature tests existentes. Não estava previsto que os Unit
tests das Actions também falhassem. A causa: policies alteradas de `?User` para `User` (T5)
fazem com que o Gate rejeite qualquer invocação sem utilizador autenticado — incluindo invocações
directas de Actions nos Unit tests.

Resolução: `beforeEach(fn () => $this->actingAs(User::factory()->create()))` em todos os
Unit tests de Actions e FormRequests. `CriarCategoriaRequestTest` teve `uses(RefreshDatabase::class)`
movido para o topo do ficheiro (o `describe` interior duplicava a declaração).

### 6. OpenAPI 3.1.0 — `nullable: true` inválido

OpenAPI 3.1.0 (alinhado com JSON Schema) não aceita `nullable: true`. A notação correcta para
campos opcionais é `type: ['string', 'null']`. Aplicado nas propriedades `links.prev` e `links.next`
da paginação cursor.

---

## Aprendizagens

### `TransientToken` vs `PersonalAccessToken` em testes

`Sanctum::actingAs($user, ['api'])` é conveniente para autenticar um utilizador nos testes mas
**não persiste nenhum token na BD**. Cria um `TransientToken` em memória. Para testes que
verificam o estado dos tokens na BD (contagem, eliminação), é necessário usar o ciclo completo:
`createToken()` → `withToken($plainTextToken)` (Feature) ou `withAccessToken($accessToken)` (Unit).
Esta distinção é invisível até ao momento em que um teste verifica persistência.

### `Gate::authorize()` é chamado mesmo em Unit tests que invocam Actions directamente

A "autorização dupla camada" (Gate na Action + Gate no FormRequest) tem uma consequência
nos testes: qualquer Unit test que invoque uma Action directamente precisa de ter um utilizador
autenticado no contexto (`actingAs()`), mesmo que o teste não esteja a testar autorização.
Sem utilizador, o Gate rejeita com "This action is unauthorized." antes de a lógica ser executada.

Isto é correcto — o Gate na Action é exactamente essa rede de segurança. O custo é que
os Unit tests precisam de `uses(RefreshDatabase::class)` e `beforeEach(actingAs)`. Uma
alternativa seria `Gate::shouldReceive('authorize')` com Mockery, mas introduz acoplamento
ao mecanismo interno e é mais frágil.

### MySQL não suporta partial indexes (WHERE clause em CREATE UNIQUE INDEX)

SQLite e PostgreSQL permitem `CREATE UNIQUE INDEX nome ON tabela(coluna) WHERE condicao`.
MySQL e MariaDB não têm esta funcionalidade. Um projecto que usa SQLite em dev e MySQL em prod
vai ter migrations que correm em dev e falham em prod. A solução é deslocar a garantia para
a Application Layer — que é onde está a lógica de domínio de qualquer forma.

### `JsonResource::$resource` é `mixed` para o Larastan nível 9

Ao criar um `JsonResource` para devolver um valor simples (ex: `string $token`), o `$this->resource`
dentro do Resource tem tipo `mixed`. O Larastan nível 9 rejeita qualquer operação sobre `mixed`
sem narrowing explícito. `(string) $this->resource` também falha porque o Larastan não aceita
cast de `mixed`. A solução preferível é não usar `JsonResource` para valores escalares — usar
`ApiResponse::devolverSucesso(['token' => $token])` é mais simples e igualmente tipável.
