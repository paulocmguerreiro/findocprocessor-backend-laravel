# System Spec — Rotas: Role + Utilizador

> Issue #50 (Role + AtribuirRole) · Issue #68 (CRUD Utilizador) · Issue #73 (Restaurar + Anonimizar)

```php
Route::apiResource('roles', RoleController::class);

Route::apiResource('utilizadores', UtilizadorController::class)
    ->parameters(['utilizadores' => 'utilizador'])
    ->withTrashed(['show', 'update', 'destroy']);
Route::put('utilizadores/{utilizador}/role', [UtilizadorController::class, 'atribuirRole']);
Route::patch('utilizadores/{utilizador}/restaurar', [UtilizadorController::class, 'restaurar'])
    ->withTrashed();
Route::post('utilizadores/{utilizador}/anonimizar', [UtilizadorController::class, 'anonimizar']);
```

Todas dentro do grupo `middleware('auth:sanctum')`.

> `->parameters([...])` força o parâmetro de rota a `{utilizador}` (alinhado com a rota `/role`).
> `->withTrashed(['show','update','destroy'])` inclui registos soft-deleted no route model binding (`destroy` exige o array explícito; `show` incluído por coerência com `index`, que expõe inactivos via `?estado=`). Ver `02-shared/soft-delete.md`.

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

## Endpoints — Utilizador

| Método | Path | Controller#método | Permission necessária |
|---|---|---|---|
| GET | `/api/utilizadores` | `UtilizadorController@index` | `utilizadores.ver` |
| POST | `/api/utilizadores` | `UtilizadorController@store` | `utilizadores.criar` |
| GET | `/api/utilizadores/{utilizador}` | `UtilizadorController@show` | `utilizadores.ver` (ou próprio) |
| PUT/PATCH | `/api/utilizadores/{utilizador}` | `UtilizadorController@update` | `utilizadores.actualizar` |
| DELETE | `/api/utilizadores/{utilizador}` | `UtilizadorController@destroy` | `utilizadores.eliminar` |
| PUT | `/api/utilizadores/{utilizador}/role` | `UtilizadorController@atribuirRole` | `utilizadores.atribuir-role` |
| PATCH | `/api/utilizadores/{utilizador}/restaurar` | `UtilizadorController@restaurar` | `utilizadores.eliminar` |
| POST | `/api/utilizadores/{utilizador}/anonimizar` | `UtilizadorController@anonimizar` | `utilizadores.anonimizar` |

Route Model Binding: `{utilizador}` → `App\Models\User` (PK inteira — **não** UUID; é o modelo de autenticação). 404 automático se não existe. `show`/`update`/`destroy`/`restaurar` resolvem **com** registos soft-deleted (`->withTrashed([...])`). `anonimizar` opera sobre utilizadores activos (sem `->withTrashed()`).

### Query params — `GET /api/utilizadores`

| Param | Tipo | Default | Restrições | Descrição |
|---|---|---|---|---|
| `per_page` | integer | 15 | 1–100 | Registos por página |
| `sort` | string | `name` | valores de `CampoOrdenacaoUtilizadores` (`name`/`email`/`created_at`) | Campo de ordenação |
| `direction` | string | `asc` | `asc`, `desc` | Direcção de ordenação |
| `estado` | string | `somente_ativos` | `todos`/`somente_ativos`/`somente_inativos` | Filtro de SoftDelete |
| `cursor` | string | — | opaco (base64) | Cursor para paginação keyset |

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
| Restaurar utilizador | 200 | `{ data: UtilizadorResource }` (`deleted_at: null`) |
| Restaurar (não inactivo / anonimizado) | 422 | `DomainException` |
| Anonimizar utilizador | 204 | — |
| Anonimizar (auto / já anonimizado) | 422 | `DomainException` |
| Role de sistema (eliminar) | 422 | `{ status: 422, detail: "Não é possível eliminar um role de sistema." }` |
| Auto-modificação de role | 422 | `{ status: 422, detail: "Não é possível alterar o próprio role." }` |
