# System Spec — Rotas: Documento

> `routes/api.php` — grupo `auth:sanctum`

---

## Endpoints implementados

| Método | Path | Action | Resposta |
|---|---|---|---|
| `GET` | `/api/documentos` | `ListarDocumentosAction` | `200` paginado `DocumentoResource[]` |
| `POST` | `/api/documentos` | `RegistarDocumentoManualAction` | `201` `DocumentoResource` |
| `POST` | `/api/documentos/upload` | `ReceberUploadDocumentoAction` | `201` `DocumentoResource` |
| `GET` | `/api/documentos/{documento}` | `VerDocumentoAction` | `200` `DocumentoResource` |
| `PATCH` | `/api/documentos/{documento}` | `CorrigirDocumentoAction` | `200` `DocumentoResource` |
| `POST` | `/api/documentos/{documento}/reprocessar` | `ReprocessarDocumentoAction` | `200` `DocumentoResource` |
| `DELETE` | `/api/documentos/{documento}` | `EliminarDocumentoAction` | `204` |
| `GET` | `/api/documentos/{documento}/ficheiro` | `DescarregarDocumentoAction` | `200` stream download |

---

## Parâmetros de query (GET /documentos)

| Param | Tipo | Valores | Notas |
|---|---|---|---|
| `per_page` | int | 1–100, default 15 | Validado no FormRequest |
| `sort` | string | `data_documento`, `created_at` | Mapeado a `CampoOrdenacaoDocumentos` |
| `direction` | string | `asc`, `desc` | Mapeado a `DirecaoOrdenacao` |
| `cursor` | string | opaco base64 | Cursor da página anterior |
| `estado` | string | valores de `EstadoDocumento` | Filtro opcional via `whereEstado` |

---

## Parâmetros de query (GET /documentos/{documento})

| Param | Tipo | Notas |
|---|---|---|
| `include` | string | `historico` — carrega `EtapaDocumento[]` via `whenLoaded` |

---

## Notas de implementação

- `POST /documentos/upload` — `multipart/form-data`; campo `ficheiro` (UploadedFile); validação por **MIME real** (`mimetypes:application/pdf,image/jpeg,image/png`) e dimensão (`max:10240` = 10 MB) no `ReceberUploadDocumentoRequest`. Rate limit dedicado `throttle:upload` (20/min — ver `02-shared/http.md`).
- `GET /documentos/{documento}/ficheiro` — o Controller faz `streamDownload` do ficheiro do disco actual do documento; o `Content-Type` é inferido do MIME do ficheiro.
- As transições de pipeline (`MarcarAnaliseMalware`, `MarcarAnaliseTexto`, `MarcarAnaliseOcr`, `MarcarAnaliseIaLocal`, `MarcarAnaliseCloud`, `TransicionarProcessado`, `MarcarErro`, `MarcarPerigoso`) **não têm endpoint** — são invocadas programaticamente pelos Commands `extracao:*` do pipeline (ver `01-features/documento-pipeline.md`).

---

## Definição em `routes/api.php`

```php
Route::post('documentos/upload', [DocumentoController::class, 'upload'])->middleware('throttle:upload');
Route::apiResource('documentos', DocumentoController::class);
Route::get('documentos/{documento}/ficheiro', [DocumentoController::class, 'descarregar']);
Route::post('documentos/{documento}/reprocessar', [DocumentoController::class, 'reprocessar']);
```

> `apiResource` gera `index`/`store`/`show`/`update`/`destroy`; o `PATCH` mapeia o método `update` do Controller, que delega na `CorrigirDocumentoAction`.
