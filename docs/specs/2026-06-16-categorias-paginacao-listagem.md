# Spec — Issue #9: Paginação na listagem de categorias

**Data:** 2026-06-16
**Issue:** [#9](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/9)
**Slug:** categorias-paginacao-listagem
**Branch:** feat/categorias-paginacao-listagem

---

## Decisão de arquitectura: cursor-based pagination (keyset)

**Padrão do sistema** — todas as listagens usam `cursorPaginate()`, nunca `paginate()` com OFFSET.

**Porquê:**
- `OFFSET N` força o SQL a percorrer e descartar N linhas — custo O(n), degrada com escala
- `WHERE nome > :cursor LIMIT N` usa o índice directamente — custo O(log n), estável
- Homogeneidade: listas futuras (documentos, etc.) seguem o mesmo contrato; filtros adicionais (ex: intervalo de datas) reduzem o conjunto antes de o cursor navegar

**Trade-off aceite:**
- Não existe `meta.total` nem `meta.last_page` (um COUNT(*) seria também O(n))
- Navegação só via `links.next` / `links.prev` — sem salto directo para página X
- Cursor é opaco (base64 gerado pelo Laravel)

---

## Contrato de API

### `GET /api/categorias-documento`

**Query params:**

| Param | Tipo | Default | Restrições |
|---|---|---|---|
| `per_page` | integer | 15 | 1–100 |
| `sort` | string | `nome` | valores do enum `CampoOrdenacaoCategorias` |
| `cursor` | string | — | opaco; gerado pelo Laravel nos `links` |

**Resposta 200:**

```json
{
  "data": [
    {
      "id": "uuid",
      "nome": "string",
      "slug": "string",
      "tipo_movimento": "debito|credito|neutro"
    }
  ],
  "links": {
    "first": null,
    "last": null,
    "prev": "http://localhost/api/categorias-documento?cursor=eyJub21lIjoiQWJj...",
    "next": "http://localhost/api/categorias-documento?cursor=eyJub21lIjoiWlh5..."
  },
  "meta": {
    "path": "http://localhost/api/categorias-documento",
    "per_page": 15,
    "next_cursor": "eyJub21lIjoiWlh5...",
    "prev_cursor": "eyJub21lIjoiQWJj..."
  }
}
```

**Resposta 422 (validação):**

```json
{
  "message": "...",
  "errors": {
    "per_page": ["O número de resultados por página não pode exceder 100."],
    "sort": ["O campo de ordenação indicado não é válido."]
  }
}
```

---

## Enum `CampoOrdenacaoCategorias`

```
namespace: App\Features\CategoriaDocumento\Listar
tipo: enum backed string
```

```php
enum CampoOrdenacaoCategorias: string
{
    case Nome = 'nome';
}
```

Extensível futuramente com `Slug`, `TipoMovimento`, etc. sem alterar o contrato do endpoint.

---

## Contratos internos

### `ListarCategoriasRequest`

```
namespace: App\Features\CategoriaDocumento\Listar
extends: Illuminate\Foundation\Http\FormRequest
```

**Rules:**
```php
[
    'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
    'sort'     => ['sometimes', 'string', Rule::in(array_column(CampoOrdenacaoCategorias::cases(), 'value'))],
    'cursor'   => ['sometimes', 'string'],
]
```

**Mensagens em PT:**
```php
[
    'per_page.integer' => 'O número de resultados por página deve ser um número inteiro.',
    'per_page.min'     => 'O número de resultados por página deve ser pelo menos 1.',
    'per_page.max'     => 'O número de resultados por página não pode exceder 100.',
    'sort.string'      => 'O campo de ordenação deve ser texto.',
    'sort.in'          => 'O campo de ordenação indicado não é válido.',
    'cursor.string'    => 'O cursor de paginação deve ser texto.',
]
```

---

### `ListarCategoriasAction`

```
namespace: App\Features\CategoriaDocumento\Listar
```

**Assinatura (depois):**
```php
/** @return \Illuminate\Pagination\CursorPaginator<int, CategoriaDocumento> */
public function handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao): \Illuminate\Pagination\CursorPaginator
```

**Body:**
```php
return CategoriaDocumento::orderBy($campoOrdenacao->value)
    ->cursorPaginate($perPage);
```

O cursor activo é resolvido automaticamente pelo Laravel a partir do query param `cursor` no request corrente.

---

### `CategoriaDocumentoController::index()`

**Assinatura (depois):**
```php
public function index(ListarCategoriasRequest $pedido, ListarCategoriasAction $accao): JsonResponse
```

**Body:**
```php
/** @var array{per_page?: int, sort?: string} $validated */
$validated = $pedido->validated();

$porPagina       = $validated['per_page'] ?? 15;
$campoOrdenacao  = CampoOrdenacaoCategorias::from($validated['sort'] ?? CampoOrdenacaoCategorias::Nome->value);

$categorias = $accao->handle($porPagina, $campoOrdenacao);

return ApiResponse::devolverPaginado(
    CategoriaDocumentoResource::collection($categorias),
);
```

---

### `ApiResponse` — novo método

```php
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

public static function devolverPaginado(AnonymousResourceCollection $coleccao): JsonResponse
{
    return $coleccao->response();
}
```

---

## Critérios de aceitação → testes

| CA | Cenário | Verificação |
|---|---|---|
| CA-01 | `GET /api/categorias-documento` (sem params) | 200, `data` com registos, `meta.per_page=15` |
| CA-02 | `GET /api/categorias-documento?per_page=101` | 422, erro em `errors.per_page` |
| CA-02b | `GET /api/categorias-documento?sort=invalido` | 422, erro em `errors.sort` |
| CA-03 | cursor além do fim (último registo já devolvido) | 200, `data=[]`, `links.next=null` |
| CA-05 | `GET /api/categorias-documento?per_page=2` com 5 registos | 200, `data` com 2 items, `links.next` não nulo |
| CA-05b | navegar via `links.next` | 200, página seguinte correcta, sem duplicados |
| CA-06 | Todos os testes incluem `assertJsonStructure` com `data`, `links` (`prev`, `next`), `meta` (`per_page`, `next_cursor`, `prev_cursor`) |

---

## Ficheiros a criar/alterar

| Operação | Ficheiro |
|---|---|
| **Criar** | `app/Features/CategoriaDocumento/Listar/ListarCategoriasRequest.php` |
| **Criar** | `app/Features/CategoriaDocumento/Listar/CampoOrdenacaoCategorias.php` |
| **Alterar** | `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php` |
| **Alterar** | `app/Features/CategoriaDocumento/CategoriaDocumentoController.php` |
| **Alterar** | `app/Shared/Http/ApiResponse.php` |
| **Alterar** | `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php` |

---

## System Spec a actualizar (Fase 3)

- `docs/system_spec/01-features.md` — assinatura de `ListarCategoriasAction::handle()`, novo enum, método `index`
- `docs/system_spec/05-routes.md` — query params `per_page`, `sort`, `cursor` no `GET /api/categorias-documento`
