# Plano — Issue #35: feat(auth): autenticação via Laravel Sanctum

**Issue:** #35
**Data:** 2026-06-19
**Slug:** auth-sanctum-api-tokens
**Spec:** `docs/specs/2026-06-19-auth-sanctum-api-tokens.md`

---

## Ordem de execução

As tarefas seguem uma dependência linear — cada bloco depende do anterior.

---

## T1 — Instalar Sanctum

**Acção:** `php artisan install:api` + `php artisan migrate`

**Ficheiros afectados:**
- `composer.json` / `composer.lock` — `laravel/sanctum` adicionado
- `config/sanctum.php` — publicado
- `database/migrations/*_create_personal_access_tokens_table.php` — criada
- `.env.example` — adicionar `SANCTUM_TOKEN_EXPIRATION=525600`

**Verificação:** Migration corre sem erro; tabela `personal_access_tokens` existe no schema.

---

## T2 — Actualizar model `User`

**Ficheiro:** `app/Models/User.php`

**Alterações:**
- Adicionar `use HasApiTokens` (trait `Laravel\Sanctum\HasApiTokens`)
- Adicionar `@property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens` ao PHPDoc

**Verificação:** `composer test:types` sem erros.

---

## T3 — Criar feature slice `Auth`

Criar os seguintes ficheiros (por esta ordem):

### T3a — DTOs / FormRequests

- `app/Features/Auth/Login/LoginRequest.php`
- `app/Features/Auth/CriarToken/CriarTokenRequest.php`

### T3b — Actions

- `app/Features/Auth/Login/LoginAction.php`
- `app/Features/Auth/Logout/LogoutAction.php`
- `app/Features/Auth/CriarToken/CriarTokenAction.php`

### T3c — Resources

- `app/Features/Auth/Login/LoginResource.php`
- `app/Features/Auth/CriarToken/CriarTokenResource.php`

### T3d — Controller

- `app/Features/Auth/AuthController.php`
  - `login(LoginRequest, LoginAction): LoginResource`
  - `logout(Request, LogoutAction): Response`
  - `criarToken(CriarTokenRequest, CriarTokenAction): CriarTokenResource`

**Verificação:** `composer test:types` sem erros.

---

## T4 — Actualizar `routes/api.php`

**Alterações:**
- `POST /auth/login` → rota pública (fora do grupo)
- Grupo `middleware('auth:sanctum')` envolve:
  - `POST /auth/logout`
  - `POST /auth/tokens`
  - `apiResource('categorias-documento', ...)`
  - `apiResource('entidades', ...)`
  - `patch('entidades/{entidade}/empresa-mae', ...)`

**Verificação:** `php artisan route:list` mostra middleware correcto em cada rota.

---

## T5 — Corrigir Policies (remover `?User`)

**Ficheiros:**
- `app/Policies/CategoriaDocumentoPolicy.php` — 5 métodos: `?User` → `User`
- `app/Policies/EntidadePolicy.php` — 5 métodos: `?User` → `User`

**Razão:** Com `auth:sanctum` no grupo, guests nunca chegam às policies. `?User` deixa de ser necessário e é semanticamente incorrecto.

**Verificação:** `composer test:types` sem erros.

---

## T6 — Actualizar testes Feature existentes

> Esta é a tarefa com mais ficheiros. Padrão uniforme: adicionar `Sanctum::actingAs()` nos happy-path; converter "guest pode" em "guest sem token recebe 401".

### T6a — CategoriaDocumento Feature tests (5 ficheiros)

Para cada ficheiro em `tests/Feature/Features/CategoriaDocumento/`:

**Nos testes de happy path** — adicionar `beforeEach` com:
```php
beforeEach(function (): void {
    $this->utilizador = User::factory()->create();
    Sanctum::actingAs($this->utilizador, ['api']);
});
```

**Nos testes `it('guest pode ...')`** — converter para:
```php
it('guest sem token recebe 401', function (): void {
    $this->postJson('/api/categorias-documento', [...])
        ->assertUnauthorized();
});
```

Ficheiros: `CriarCategoriaTest`, `ListarCategoriasTest`, `VerCategoriaTest`, `ActualizarCategoriaTest`, `EliminarCategoriaTest`

### T6b — Entidade Feature tests (6 ficheiros)

Mesmo padrão — ficheiros: `CriarEntidadeTest`, `ListarEntidadesTest`, `VerEntidadeTest`, `ActualizarEntidadeTest`, `EliminarEntidadeTest`, `ConverterEmEmpresaMaeTest`

### T6c — Shared Feature tests

Verificar `ApiResponseTest` e `ExceptionHandlerTest` — se testam rotas da API, adicionar `Sanctum::actingAs()`.

---

## T7 — Actualizar `EntidadePolicyTest`

**Ficheiro:** `tests/Unit/Policies/EntidadePolicyTest.php`

**Alterações:**
- Remover o `describe('Guest (policy placeholder — sem restrições)')` inteiro (5 testes) — guests nunca chegam à policy com `auth:sanctum`
- O `describe('Utilizador autenticado')` mantém-se sem alterações

**Nota:** Não existe `CategoriaDocumentoPolicyTest` — não há nada a fazer para policies de categoria.

---

## T8 — Criar testes Auth (padrão dual)

### T8a — Unit tests

**`tests/Unit/Features/Auth/LoginActionTest.php`**
- `login com credenciais correctas devolve string de token`
- `login com email inexistente lança ValidationException`
- `login com password incorrecta lança ValidationException`

**`tests/Unit/Features/Auth/LogoutActionTest.php`**
- `logout revoga o token actual do utilizador`

**`tests/Unit/Features/Auth/CriarTokenActionTest.php`**
- `criar token devolve string de token com ability api`

### T8b — Feature tests (HTTP)

**`tests/Feature/Features/Auth/LoginTest.php`**
- `POST /auth/login com credenciais válidas devolve 200 com token`
- `POST /auth/login com credenciais inválidas devolve 422`
- `POST /auth/login sem campos devolve 422`

**`tests/Feature/Features/Auth/LogoutTest.php`**
- `POST /auth/logout sem token devolve 401`
- `POST /auth/logout com token válido devolve 204 e revoga token`

**`tests/Feature/Features/Auth/CriarTokenTest.php`**
- `POST /auth/tokens sem autenticação devolve 401`
- `POST /auth/tokens com autenticação devolve 200 com token`

**`tests/Feature/Features/Auth/AcessoProtegidoTest.php`** (regressão)
- `GET /api/categorias-documento sem token devolve 401`
- `GET /api/categorias-documento com token válido devolve 200`

---

## T9 — Actualizar `openapi.yaml`

**Localização:** raiz do projecto ou `docs/openapi.yaml` (verificar no T9).

**Alterações:**
- `components.securitySchemes` — adicionar:
  ```yaml
  bearerAuth:
    type: http
    scheme: bearer
    bearerFormat: JWT
  ```
- Em todas as rotas protegidas — adicionar:
  ```yaml
  security:
    - bearerAuth: []
  ```
- `POST /auth/login` — sem `security` (pública)
- Novos paths: `POST /auth/login`, `POST /auth/logout`, `POST /auth/tokens`

---

## T10 — Actualizar SYSTEM_SPEC

**Ficheiros a criar/actualizar:**
- `docs/system_spec/00-index.md` — adicionar `Auth` em features implementadas + `05-routes/auth.md`
- `docs/system_spec/01-features/auth.md` — criar (LoginAction, LogoutAction, CriarTokenAction)
- `docs/system_spec/05-routes/auth.md` — criar (3 rotas + middleware)
- `docs/system_spec/03-models/user.md` — criar (User + HasApiTokens, excepção UUID documentada)

---

## T11 — Pipeline de qualidade

```bash
composer lint      # Pint — formatação
composer refactor  # Rector — modernizações
composer test      # Pipeline completa
```

Corrigir todos os erros antes de finalizar.

---

## Resumo de ficheiros

| Acção | Ficheiro |
|---|---|
| Instalar | `composer.json`, `config/sanctum.php`, migration `personal_access_tokens`, `.env.example` |
| Alterar | `app/Models/User.php` |
| Criar | `app/Features/Auth/**` (7 ficheiros) |
| Alterar | `routes/api.php` |
| Alterar | `app/Policies/CategoriaDocumentoPolicy.php`, `app/Policies/EntidadePolicy.php` |
| Alterar | 11 ficheiros de Feature tests existentes |
| Alterar | `tests/Unit/Policies/EntidadePolicyTest.php` |
| Criar | 8 ficheiros de testes Auth (3 Unit + 5 Feature) |
| Alterar | `openapi.yaml` |
| Criar/Alterar | 4 ficheiros SYSTEM_SPEC |
