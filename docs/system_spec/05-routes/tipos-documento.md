# System Spec — Rotas: TipoDocumento

> Issues #84 (modelo), #85 (lógica)

```php
Route::apiResource('tipos-documento', TipoDocumentoController::class)
    ->only(['index', 'store', 'show', 'update', 'destroy']);
```

Sem `withTrashed()`, sem rota `/restaurar` — `TipoDocumento` não tem `SoftDeletes`.

---

## Endpoints

| Método | Path | Controller#método | Parâmetro de rota |
|---|---|---|---|
| GET | `/api/tipos-documento` | `TipoDocumentoController@index` | — (ver query params abaixo) |
| POST | `/api/tipos-documento` | `TipoDocumentoController@store` | — |
| GET | `/api/tipos-documento/{tipos_documento}` | `TipoDocumentoController@show` | UUID (RMB) |
| PUT/PATCH | `/api/tipos-documento/{tipos_documento}` | `TipoDocumentoController@update` | UUID (RMB) |
| DELETE | `/api/tipos-documento/{tipos_documento}` | `TipoDocumentoController@destroy` | UUID (RMB) |

Route Model Binding: `{tipos_documento}` → `TipoDocumento` (resolvido via `HasUuids`). 404 automático se UUID não existe (sem `withTrashed`, porque não há soft delete).

---

## Query params — `GET /api/tipos-documento`

| Param | Tipo | Default | Restrições | Descrição |
|---|---|---|---|---|
| `per_page` | integer | 15 | 1–100 | Registos por página |
| `sort` | string | `nome` | valores de `CampoOrdenacaoTiposDocumento` | Campo de ordenação |
| `direction` | string | `asc` | `asc`, `desc` | Direcção de ordenação |
| `cursor` | string | — | opaco (base64) | Cursor gerado pelo Laravel; navegar via `links.next` / `links.prev` |
| `id_categoria` | string (uuid) | — | opcional; se fornecido, `Rule::exists('categorias_documento', 'id')` | Filtra por categoria; 422 se fornecido mas inexistente; sem filtro se omitido |

---

## Corpo do pedido — `POST` / `PUT /api/tipos-documento/{id}`

| Campo | Tipo | Obrigatório | Restrições |
|---|---|---|---|
| `nome` | string | sim | `max:255`, único (`Rule::unique`, `->ignore($uuid)` em `PUT`) |
| `descricao` | string | sim | — |
| `id_categoria` | string (uuid) | sim | `Rule::exists('categorias_documento', 'id')` |
| `posicao_empresa_mae` | string | sim | `Rule::in(PosicaoEmpresaMae::cases())` |
| `espera_data_documento` | boolean | sim | — |
| `espera_fornecedor` | boolean | sim | — |
| `espera_cliente` | boolean | sim | — |
| `espera_valor` | boolean | sim | — |

**Regra cross-field (RN-02):** pelo menos um dos 4 `espera_*` tem de ser `true` — validada via `withValidator()` (422, erro em `espera_data_documento`), ver `01-features/tipo-documento.md`.

---

## Resposta 200 (cursor pagination)

```json
{
  "data": [{ "id": "uuid", "nome": "...", "descricao": "...", "categoria": {...}, "tipo_movimento": "...", "posicao_empresa_mae": "...", "espera_data_documento": true, "espera_fornecedor": true, "espera_cliente": false, "espera_valor": true, "criado_em": "...", "actualizado_em": "..." }],
  "links": { "first": null, "last": null, "prev": "...|null", "next": "...|null" },
  "meta": { "path": "...", "per_page": 15, "next_cursor": "...|null", "prev_cursor": "...|null" }
}
```

`meta.total` e `meta.last_page` não existem — cursor pagination não faz COUNT(*).
