# System Spec — Rotas: CategoriaDocumento

> Issues #5, #9

`Route::apiResource('categorias-documento', CategoriaDocumentoController::class)`

---

## Endpoints

| Método | Path | Controller#método | Parâmetro de rota |
|---|---|---|---|
| GET | `/api/categorias-documento` | `CategoriaDocumentoController@index` | — (ver query params abaixo) |
| POST | `/api/categorias-documento` | `CategoriaDocumentoController@store` | — |
| GET | `/api/categorias-documento/{categorias_documento}` | `CategoriaDocumentoController@show` | UUID (RMB) |
| PUT/PATCH | `/api/categorias-documento/{categorias_documento}` | `CategoriaDocumentoController@update` | UUID (RMB) |
| DELETE | `/api/categorias-documento/{categorias_documento}` | `CategoriaDocumentoController@destroy` | UUID (RMB) |

Route Model Binding: `{categorias_documento}` → `CategoriaDocumento` (resolvido via `HasUuids`). 404 automático se UUID não existe.

---

## Query params — `GET /api/categorias-documento`

| Param | Tipo | Default | Restrições | Descrição |
|---|---|---|---|---|
| `per_page` | integer | 15 | 1–100 | Registos por página |
| `sort` | string | `nome` | valores de `CampoOrdenacaoCategorias` | Campo de ordenação |
| `direction` | string | `asc` | `asc`, `desc` | Direcção de ordenação |
| `cursor` | string | — | opaco (base64) | Cursor gerado pelo Laravel; navegar via `links.next` / `links.prev` |

---

## Resposta 200 (cursor pagination)

```json
{
  "data": [{ "id": "uuid", "nome": "...", "slug": "...", "tipo_movimento": "..." }],
  "links": { "first": null, "last": null, "prev": "...|null", "next": "...|null" },
  "meta": { "path": "...", "per_page": 15, "next_cursor": "...|null", "prev_cursor": "...|null" }
}
```

`meta.total` e `meta.last_page` não existem — cursor pagination não faz COUNT(*).
