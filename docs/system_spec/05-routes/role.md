# System Spec — Rotas: Role + Utilizador

> Issue #50

```php
Route::apiResource('roles', RoleController::class);
Route::put('utilizadores/{utilizador}/role', [UtilizadorController::class, 'atribuirRole']);
```

Todas dentro do grupo `middleware('auth:sanctum')`.

---

## Endpoints — Role

| Método | Path | Controller#método | Permission necessária |
|---|---|---|---|
| GET | `/api/roles` | `RoleController@index` | `roles.ver` |
| POST | `/api/roles` | `RoleController@store` | `roles.criar` |
| GET | `/api/roles/{role}` | `RoleController@show` | `roles.ver` |
| PUT/PATCH | `/api/roles/{role}` | `RoleController@update` | `roles.actualizar` |
| DELETE | `/api/roles/{role}` | `RoleController@destroy` | `roles.eliminar` |

Route Model Binding: `{role}` → `Spatie\Permission\Models\Role` (ID inteiro). 404 automático se não existe.

---

## Endpoint — Utilizador

| Método | Path | Controller#método | Permission necessária |
|---|---|---|---|
| PUT | `/api/utilizadores/{utilizador}/role` | `UtilizadorController@atribuirRole` | `utilizadores.atribuir-role` |

Route Model Binding: `{utilizador}` → `App\Models\User` (UUID via `HasUuids`). 404 automático se não existe.

---

## Query params — `GET /api/roles`

| Param | Tipo | Default | Restrições | Descrição |
|---|---|---|---|---|
| `per_page` | integer | 15 | 1–100 | Registos por página |
| `sort` | string | `name` | valores de `CampoOrdenacaoRoles` | Campo de ordenação |
| `direction` | string | `asc` | `asc`, `desc` | Direcção de ordenação |
| `cursor` | string | — | opaco (base64) | Cursor para paginação keyset |

---

## Respostas

| Operação | Status | Body |
|---|---|---|
| Listar | 200 | `{ data: [...], meta: {...} }` (cursor paginator) |
| Ver | 200 | `{ data: { id, nome, permissoes } }` |
| Criar | 201 | `{ data: { id, nome, permissoes } }` |
| Actualizar | 200 | `{ data: { id, nome, permissoes } }` |
| Eliminar | 204 | — |
| Atribuir role | 204 | — |
| Role de sistema (eliminar) | 422 | `{ status: 422, detail: "Não é possível eliminar um role de sistema." }` |
| Auto-modificação de role | 422 | `{ status: 422, detail: "Não é possível alterar o próprio role." }` |
