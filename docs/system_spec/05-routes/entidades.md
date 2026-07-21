# System Spec — Rotas: Entidade

```php
Route::apiResource('entidades', EntidadeController::class)
    ->withTrashed(['show', 'update', 'destroy'])
Route::patch('entidades/{entidade}/restaurar', [EntidadeController::class, 'restaurar'])
    ->withTrashed()
Route::patch('entidades/{entidade}/empresa-mae', [EntidadeController::class, 'converterEmEmpresaMae'])
Route::post('entidades/{principal}/agrupar-com/{secundaria}', [EntidadeController::class, 'agruparCom'])
```

---

## Endpoints

| Método | Path | Controller#método | Parâmetro de rota |
|---|---|---|---|
| GET | `/api/entidades` | `EntidadeController@index` | — (ver query params abaixo) |
| POST | `/api/entidades` | `EntidadeController@store` | — |
| GET | `/api/entidades/{entidade}` | `EntidadeController@show` | UUID (RMB `withTrashed`) |
| PUT/PATCH | `/api/entidades/{entidade}` | `EntidadeController@update` | UUID (RMB `withTrashed`) |
| DELETE | `/api/entidades/{entidade}` | `EntidadeController@destroy` | UUID (RMB `withTrashed`) |
| PATCH | `/api/entidades/{entidade}/restaurar` | `EntidadeController@restaurar` | UUID (RMB `withTrashed`) |
| PATCH | `/api/entidades/{entidade}/empresa-mae` | `EntidadeController@converterEmEmpresaMae` | UUID (RMB) |
| POST | `/api/entidades/{principal}/agrupar-com/{secundaria}` | `EntidadeController@agruparCom` | 2 UUIDs (RMB nomeado, **sem** `withTrashed` — inexistente ou soft-deleted → 404) |

Route Model Binding: `{entidade}` → `Entidade` (resolvido via `HasUuids`). 404 automático se UUID não existe.

**SoftDeletes:** `show`/`update`/`destroy` e `restaurar` usam `->withTrashed()` para o binding incluir registos soft-deleted — sem isto, restaurar/ver um inactivo daria 404. `index` mantém-se activo-por-omissão (filtra via `?estado=`). `agrupar-com` **não** usa `withTrashed` em nenhum dos dois parâmetros — principal ou secundária soft-deleted dá 404 (RN-03). Padrão completo em `02-shared/soft-delete.md`.

**`agrupar-com` — corpo vazio.** A operação funde `secundaria` em `principal` (reponta FKs conhecidas, une papéis por OR, hard-delete da secundária) numa única transação. Erros de negócio (`principal == secundaria`, secundária = empresa aplicação, FK nova não tratada) → `422`. Detalhe da Action: `01-features/entidade.md`.

---

## Query params — `GET /api/entidades`

| Param | Tipo | Default | Restrições | Descrição |
|---|---|---|---|---|
| `per_page` | integer | 15 | 1–100 | Registos por página |
| `sort` | string | `nome` | valores de `CampoOrdenacaoEntidades` | Campo de ordenação |
| `direction` | string | `asc` | `asc`, `desc` | Direcção de ordenação |
| `estado` | string | `somente_ativos` | valores de `FiltroEstadoRegisto` (`todos`, `somente_ativos`, `somente_inativos`) | Filtro de estado SoftDelete |
| `cursor` | string | — | opaco (base64) | Cursor para paginação keyset |
