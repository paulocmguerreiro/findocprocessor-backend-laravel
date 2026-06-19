# System Spec — Rotas: Entidade

> Issue #40

```php
Route::apiResource('entidades', EntidadeController::class)
Route::patch('entidades/{entidade}/empresa-mae', [EntidadeController::class, 'converterEmEmpresaMae'])
```

---

## Endpoints

| Método | Path | Controller#método | Parâmetro de rota |
|---|---|---|---|
| GET | `/api/entidades` | `EntidadeController@index` | — (ver query params abaixo) |
| POST | `/api/entidades` | `EntidadeController@store` | — |
| GET | `/api/entidades/{entidade}` | `EntidadeController@show` | UUID (RMB) |
| PUT/PATCH | `/api/entidades/{entidade}` | `EntidadeController@update` | UUID (RMB) |
| DELETE | `/api/entidades/{entidade}` | `EntidadeController@destroy` | UUID (RMB) |
| PATCH | `/api/entidades/{entidade}/empresa-mae` | `EntidadeController@converterEmEmpresaMae` | UUID (RMB) |

Route Model Binding: `{entidade}` → `Entidade` (resolvido via `HasUuids`). 404 automático se UUID não existe.

---

## Query params — `GET /api/entidades`

| Param | Tipo | Default | Restrições | Descrição |
|---|---|---|---|---|
| `per_page` | integer | 15 | 1–100 | Registos por página |
| `sort` | string | `nome` | valores de `CampoOrdenacaoEntidades` | Campo de ordenação |
| `direction` | string | `asc` | `asc`, `desc` | Direcção de ordenação |
| `cursor` | string | — | opaco (base64) | Cursor para paginação keyset |
