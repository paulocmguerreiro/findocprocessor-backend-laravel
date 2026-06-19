# System Spec — 05: Rotas API

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## routes/api.php (implementado)

### CategoriaDocumento (Issue #5, #9)

`Route::apiResource('categorias-documento', CategoriaDocumentoController::class)`

| Método | Path | Controller#método | Parâmetro de rota |
|---|---|---|---|
| GET | `/api/categorias-documento` | `CategoriaDocumentoController@index` | — (ver query params abaixo) |
| POST | `/api/categorias-documento` | `CategoriaDocumentoController@store` | — |
| GET | `/api/categorias-documento/{categorias_documento}` | `CategoriaDocumentoController@show` | UUID (RMB) |
| PUT/PATCH | `/api/categorias-documento/{categorias_documento}` | `CategoriaDocumentoController@update` | UUID (RMB) |
| DELETE | `/api/categorias-documento/{categorias_documento}` | `CategoriaDocumentoController@destroy` | UUID (RMB) |

Route Model Binding: `{categorias_documento}` → `CategoriaDocumento` (resolvido via `HasUuids`). 404 automático se UUID não existe.

**Query params — `GET /api/categorias-documento` (Issue #9):**

| Param | Tipo | Default | Restrições | Descrição |
|---|---|---|---|---|
| `per_page` | integer | 15 | 1–100 | Registos por página |
| `sort` | string | `nome` | valores de `CampoOrdenacaoCategorias` | Campo de ordenação |
| `direction` | string | `asc` | `asc`, `desc` | Direcção de ordenação |
| `cursor` | string | — | opaco (base64) | Cursor gerado pelo Laravel; navegar via `links.next` / `links.prev` |

**Resposta 200 (cursor pagination):**
```json
{
  "data": [{ "id": "uuid", "nome": "...", "slug": "...", "tipo_movimento": "..." }],
  "links": { "first": null, "last": null, "prev": "...|null", "next": "...|null" },
  "meta": { "path": "...", "per_page": 15, "next_cursor": "...|null", "prev_cursor": "...|null" }
}
```
Nota: `meta.total` e `meta.last_page` não existem — cursor pagination não faz COUNT(*).

---

### Entidade (Issue #40)

`Route::apiResource('entidades', EntidadeController::class)`
`Route::patch('entidades/{entidade}/empresa-mae', [EntidadeController::class, 'converterEmEmpresaMae'])`

| Método | Path | Controller#método | Parâmetro de rota |
|---|---|---|---|
| GET | `/api/entidades` | `EntidadeController@index` | — (ver query params abaixo) |
| POST | `/api/entidades` | `EntidadeController@store` | — |
| GET | `/api/entidades/{entidade}` | `EntidadeController@show` | UUID (RMB) |
| PUT/PATCH | `/api/entidades/{entidade}` | `EntidadeController@update` | UUID (RMB) |
| DELETE | `/api/entidades/{entidade}` | `EntidadeController@destroy` | UUID (RMB) |
| PATCH | `/api/entidades/{entidade}/empresa-mae` | `EntidadeController@converterEmEmpresaMae` | UUID (RMB) |

Route Model Binding: `{entidade}` → `Entidade` (resolvido via `HasUuids`). 404 automático se UUID não existe.

**Query params — `GET /api/entidades`:**

| Param | Tipo | Default | Restrições | Descrição |
|---|---|---|---|---|
| `per_page` | integer | 15 | 1–100 | Registos por página |
| `sort` | string | `nome` | valores de `CampoOrdenacaoEntidades` | Campo de ordenação |
| `direction` | string | `asc` | `asc`, `desc` | Direcção de ordenação |
| `cursor` | string | — | opaco (base64) | Cursor para paginação keyset |

---

## routes/api.php (planeado)

| Método | Path                               | Controller/Action            | Estado   |
| ------ | ---------------------------------- | ---------------------------- | -------- |
| GET    | `/state`                           | DocumentController           | pendente |
| GET    | `/events`                          | SseController                | pendente |
| GET    | `/config`                          | ConfigController             | pendente |
| GET    | `/files/list`                      | FileController               | pendente |
| POST   | `/files/open`                      | FileController               | pendente |
| POST   | `/upload`                          | UploadController             | pendente |
| POST   | `/documents/manual`                | DocumentController           | pendente |
| POST   | `/action/correct`                  | DocumentController           | pendente |
| POST   | `/action/delete-error`             | DocumentController           | pendente |
| POST   | `/action/delete-done`              | DocumentController           | pendente |
| POST   | `/action/reset-error`              | DocumentController           | pendente |
| POST   | `/action/force-cycle`              | BatchController              | pendente |
| GET    | `/config/extraction-templates`     | ConfigController             | pendente |
| POST   | `/config/extraction-templates`     | ConfigController             | pendente |
| PUT    | `/config/extraction-templates/{id}`| ConfigController             | pendente |
