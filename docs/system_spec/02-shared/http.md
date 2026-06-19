# System Spec — Shared: HTTP

> `app/Shared/Http/`

---

## `ApiResponse` — `App\Shared\Http\ApiResponse`

Factory estática `final` para respostas de sucesso. Único ponto de saída de respostas nos controllers.

| Método | HTTP | Estrutura |
|---|---|---|
| `devolverSucesso(JsonResource $recurso): JsonResponse` | 200 | `{ "data": { ... } }` |
| `devolverCriado(JsonResource $recurso): JsonResponse` | 201 | `{ "data": { ... } }` |
| `devolverVazio(): JsonResponse` | 204 | body vazio |
| `devolverPaginado(AnonymousResourceCollection $coleccao): JsonResponse` | 200 | `{ "data": [...], "links": {...}, "meta": {...} }` — cursor pagination |
| `devolverColeccao(ResourceCollection $coleccao, array $meta = []): JsonResponse` | 200 | `{ "data": [...], "meta": { ... } }` |

- `devolverPaginado` delega em `$coleccao->response()` — o Laravel resolve automaticamente `links` e `meta` do `CursorPaginator`
- `$meta` de `devolverColeccao` é fornecido explicitamente pelo caller (uso para colecções não paginadas)
- Não injectable — formatação pura sem lógica de negócio
- Classe `final` — não extensível

### Payloads completos por método

**`devolverSucesso` / `devolverCriado`** (200 / 201):
```json
{
  "data": {
    "id": "019741b2-...",
    "nome": "Fatura de Fornecedor",
    "slug": "fatura-de-fornecedor",
    "tipo_movimento": "debito"
  }
}
```

**`devolverVazio`** (204): body vazio, sem conteúdo.

**`devolverPaginado`** (200 — cursor pagination):
```json
{
  "data": [
    { "id": "019741b2-...", "nome": "Fatura", "slug": "fatura", "tipo_movimento": "debito" }
  ],
  "links": {
    "first": null,
    "last": null,
    "prev": "https://.../api/categorias-documento?cursor=eyJpZCI6...",
    "next": "https://.../api/categorias-documento?cursor=eyJpZCI6..."
  },
  "meta": {
    "path": "https://.../api/categorias-documento",
    "per_page": 15,
    "next_cursor": "eyJpZCI6...",
    "prev_cursor": null
  }
}
```

**`devolverColeccao`** (200 — colecção não paginada):
```json
{
  "data": [ { "id": "...", "nome": "..." } ],
  "meta": { "total": 3 }
}
```

---

## Cursor pagination — convenção obrigatória

Todas as listagens da API usam **cursor pagination (keyset)** — `cursorPaginate()`. **Nunca** `paginate()` com OFFSET.

| Aspecto | Regra |
|---|---|
| Método de paginação | `cursorPaginate()` — keyset, estável sob inserções concorrentes |
| OFFSET (`paginate()`, `simplePaginate()`) | **Proibido** — degrada em datasets grandes e produz duplicados/saltos |
| Tamanho de página | parâmetro `per_page` no request; valor máximo validado (≤ 100) no FormRequest |
| Ordenação | campo de ordenação exposto como **enum** (nunca string livre) + direcção via `DirecaoOrdenacao` |
| Cursor | opaco, base64 — o cliente nunca o constrói; segue `links.next` / `links.prev` |

Razões: o keyset não usa `OFFSET N`, logo não relê N linhas a cada página, e mantém-se correcto mesmo que registos sejam inseridos/removidos entre pedidos.

A resposta de uma listagem é sempre produzida por `ApiResponse::devolverPaginado()`.

---

## Exception Handler (`bootstrap/app.php`)

Configurado via `withExceptions()`. Cinco closures `render()` por ordem de especificidade.

Todos os handlers verificam `$request->expectsJson()` — devolvem `null` para requests HTML.

**Payload de erro (Problem Details RFC 7807 simplificado):**

```json
{ "status": <int>, "detail": "<string PT>" }
// 422 inclui também: "errors": { "campo": ["mensagem"] }
```

**Mapeamento de excepções:**

| Excepção no closure | HTTP | `detail` |
|---|---|---|
| `ValidationException` | 422 | "Os dados fornecidos são inválidos." + `errors` por campo |
| `NotFoundHttpException` | 404 | "Recurso não encontrado." |
| `AccessDeniedHttpException` | 403 | "Sem permissão para aceder a este recurso." |
| `AuthenticationException` | 401 | "Não autenticado." |
| `Throwable` (fallback) | 500 | "Ocorreu um erro interno. Tente novamente mais tarde." |

> **Nota:** O Laravel converte `ModelNotFoundException` → `NotFoundHttpException` e `AuthorizationException` → `AccessDeniedHttpException` antes de invocar os callbacks (`prepareException()`). Os closures usam os tipos Symfony convertidos.

Stack traces nunca incluídos na resposta.

---

## Exceptions (`app/Shared/Exceptions/`)

_Vazio até à primeira issue implementada._
