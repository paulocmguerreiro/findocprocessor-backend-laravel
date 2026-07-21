# System Spec — Rotas: Auth

> `routes/api.php`

## Rotas

| Método | Path | Controller | Middleware | Resposta |
|---|---|---|---|---|
| POST | `/auth/login` | `AuthController@login` | nenhum (pública) | 200 + token |
| POST | `/auth/logout` | `AuthController@logout` | `auth:sanctum` | 204 |
| POST | `/auth/tokens` | `AuthController@criarToken` | `auth:sanctum` | 200 + token |

## Middleware global

Todas as rotas da API excepto `/auth/login` estão dentro do grupo `Route::middleware('auth:sanctum')` — incluindo `/categorias-documento` e `/entidades`. Pedidos sem token recebem 401 (`AuthenticationException` → handler em `bootstrap/app.php`).
