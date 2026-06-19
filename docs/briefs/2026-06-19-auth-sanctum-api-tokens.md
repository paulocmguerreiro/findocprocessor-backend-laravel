# Brief — Issue #35: feat(auth): autenticação via Laravel Sanctum

**Issue:** #35
**Data:** 2026-06-19
**Slug:** auth-sanctum-api-tokens
**Branch:** feat/auth-sanctum-api-tokens
**Tipo:** feat
**Prioridade:** P1

---

## Contexto

As rotas da API não têm autenticação — qualquer pedido não autenticado acede e modifica dados livremente. Esta issue instala e configura o Laravel Sanctum (API tokens Bearer) e cria a feature slice `Auth` com as Actions de login, logout e criação de token. É a base obrigatória para a camada de autorização.

---

## Problema concreto

- `routes/api.php` não tem `middleware('auth:sanctum')` — todas as rotas são públicas.
- O model `User` não tem `HasApiTokens` — tokens não podem ser emitidos.
- Não existe feature slice `Auth` com Actions de login/logout.
- `laravel/sanctum` não está no `composer.json`.

---

## Solução adoptada

### Instalação
Usar `php artisan install:api` (comando nativo do Laravel 13 — instala Sanctum e publica migration `personal_access_tokens`). Não usar a abordagem legada com `vendor:publish --provider`.

### Feature slice Auth
Criar `app/Features/Auth/` com três Actions:
- `LoginAction` — valida credenciais, emite token Bearer
- `LogoutAction` — revoga o token actual do utilizador autenticado
- `CriarTokenAction` — cria token adicional para utilizador já autenticado

### User model
Adicionar trait `HasApiTokens` e actualizar `@property-read` para incluir a relação `tokens`.

> **Nota:** O model `User` é o modelo de autenticação Laravel — usa `int $id` autoincremental (não UUID). Esta é uma excepção documentada e intencional: os modelos de domínio usam `HasUuids`, o `User` mantém a PK inteira por compatibilidade com o Laravel Auth e Sanctum.

### Rotas
- `POST /auth/login` — pública (sem middleware)
- `POST /auth/logout` — protegida com `auth:sanctum`
- Todas as rotas existentes (categorias, entidades) protegidas com `auth:sanctum`

### Abilities
Token emitido com `abilities: ['api']` para suporte futuro de scopes.

---

## Decisões de arquitectura

### Repository
Dispensável. `LoginAction` e `LogoutAction` fazem CRUD simples sobre `User` e relação `tokens`. Sem lógica de query complexa — critério CRUD simples satisfeito (ver `04-infra/repositories.md`).

### Autorização nas Actions
- `LoginAction` — **sem** `Gate::authorize()`: é uma acção pública (não requer utilizador autenticado).
- `LogoutAction` — o utilizador autenticado revoga o **seu próprio** token. Gate trivial — não há Policy de `PersonalAccessToken`. O middleware `auth:sanctum` já garante autenticação. Gate desnecessário neste caso.
- `CriarTokenAction` — idem: protegida por middleware, sem Policy adicional.

### Transação em `LoginAction`
A emissão do token com `createToken()` é escrita na BD — `DB::transaction()` obrigatório (ver `04-infra/transactions.md`).

### Testes
Padrão dual obrigatório (ver `07-testing.md`):
- `tests/Unit/Features/Auth/` — invocação directa das Actions
- `tests/Feature/Features/Auth/` — via HTTP
- Usar `Sanctum::actingAs($user, ['api'])` nos testes que precisam de autenticação

---

## Riscos identificados

| Risco | Mitigação |
|---|---|
| `install:api` pode conflituar com migration existente se `personal_access_tokens` já existir | Verificar schema antes — BD está vazia em dev |
| User sem `HasApiTokens` causa `BadMethodCallException` em runtime | Adicionar antes de qualquer teste |
| Sanctum em modo SPA usa session cookies — **não queremos** | Não chamar `$middleware->statefulApi()` no `bootstrap/app.php` |
| Token TTL a zero = tokens eternos | Definir `SANCTUM_TOKEN_EXPIRATION` no `.env` |
| AuthenticationException já tratada no `bootstrap/app.php` com 401 JSON | Compatível — não precisa de alteração |

---

## Questões em aberto

| Questão | Resposta |
|---|---|
| `CriarTokenAction` é necessária agora ou é fora de âmbito? | Issue menciona-a como CA-03 — incluir |
| TTL padrão do token? | Configurável via `SANCTUM_TOKEN_EXPIRATION` — definir na `.env.example` |
| `openapi.yaml` a actualizar? | Sim (CA indica breaking change) — fora de âmbito desta implementação base; issue nota que é obrigatório mas sem teste a bloquear |

---

## Impacto

- **Ficheiros alterados:** `app/Models/User.php`, `routes/api.php`, `bootstrap/app.php` (se necessário), `config/sanctum.php` (publicado), migration `personal_access_tokens`
- **Ficheiros criados:** `app/Features/Auth/` (Controller + 3 Actions + 2 FormRequests + Resource), testes
- **SYSTEM_SPEC a actualizar:** `00-index.md`, `01-features/auth.md` (novo), `05-routes/auth.md` (novo)
- **Breaking change:** sim — rotas existentes passam a exigir `Authorization: Bearer <token>`
