# System Spec — Feature: Auth

> `app/Features/Auth/`

Autenticação via Laravel Sanctum — API tokens Bearer. Base obrigatória para a camada de autorização.

---

## Actions

| Action | Ficheiro | Método | Gate? | Transaction? |
|---|---|---|---|---|
| `LoginAction` | `Login/LoginAction.php` | `handle(string $email, string $password): string` | Não — acção pública | Sim |
| `LogoutAction` | `Logout/LogoutAction.php` | `handle(User $utilizador): void` | Não — middleware garante auth | Sim |
| `CriarTokenAction` | `CriarToken/CriarTokenAction.php` | `handle(User $utilizador, string $nomeToken): string` | Não — middleware garante auth | Sim |

---

## Decisões de arquitectura

- **`LoginAction` sem `Gate::authorize()`** — é uma acção pública (utilizador ainda não autenticado).
- **`LogoutAction` e `CriarTokenAction` sem `Gate::authorize()`** — o middleware `auth:sanctum` na rota garante autenticação antes de chegar à Action; utilizador opera sobre os seus próprios tokens (sem Policy de `PersonalAccessToken`).
- **Sem Repository** — CRUD simples sobre `User` e relação `tokens`; critério de dispensa satisfeito.
- **Abilities** — todos os tokens emitidos com `['api']` para suporte futuro de scopes.
- **Sem modo SPA** — nunca chamar `statefulApi()` no `bootstrap/app.php`; este projecto usa exclusivamente Bearer tokens.

---

## Expiração de tokens

- **Janela global: 8 horas** — `config/sanctum.php` → `'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 480)` (minutos). Antes era `null` (nunca expiravam).
- **Limpeza:** `Schedule::command('sanctum:prune-expired --hours=24')->daily()` em `routes/console.php` remove os registos de tokens expirados há mais de 24h.
- **Aplica-se a todos os tokens** (login e PATs de `POST /auth/tokens`) de forma uniforme. Se, no futuro, os PATs de integração precisarem de uma janela mais longa, o caminho é passar um `expires_at` por token via `createToken($nome, ['api'], $expiresAt)` e retirar a expiração global (o valor global sobrepõe-se ao `expires_at` do token) — não implementado por não haver necessidade actual.

---

## FormRequests

| Request | Campos validados |
|---|---|
| `LoginRequest` | `email` (required, email), `password` (required, string) |
| `CriarTokenRequest` | `nome_token` (required, string, max:255) |

`LogoutAction` não tem FormRequest — usa `Request` directamente (sem body).

---

## Controller

`AuthController` — 3 métodos, sem lógica, delega para Actions, responde via `ApiResponse`.

| Método | Rota | Resposta |
|---|---|---|
| `login` | `POST /auth/login` | `ApiResponse::devolverSucesso(['token' => $token])` → 200 |
| `logout` | `POST /auth/logout` | `ApiResponse::devolverVazio()` → 204 |
| `criarToken` | `POST /auth/tokens` | `ApiResponse::devolverSucesso(['token' => $token])` → 200 |

---

## Notas sobre o model User

O model `User` é o modelo de autenticação Laravel — usa PK `int $id` autoincremental (não UUID). Excepção intencional e documentada: não é um modelo de domínio. Ver `03-models/user.md`.
