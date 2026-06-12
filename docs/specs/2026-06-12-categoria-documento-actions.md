# Spec — Issue #5: CategoriaDocumento — Actions + Controller

**Data:** 2026-06-12
**Branch:** `feat/categoria-documento-actions`
**Issue:** #5

---

## 1. DTOs

### `CriarCategoriaDto`
**Namespace:** `App\Features\CategoriaDocumento\Criar`

```php
final readonly class CriarCategoriaDto
{
    public function __construct(
        public string $nome,
        public string $slug,
        public TipoMovimento $tipo_movimento,
    ) {}

    public static function fromRequest(CriarCategoriaRequest $request): self
    {
        return new self(
            nome: $request->validated('nome'),
            slug: $request->validated('slug'),
            tipo_movimento: TipoMovimento::from($request->validated('tipo_movimento')),
        );
    }
}
```

### `ActualizarCategoriaDto`
**Namespace:** `App\Features\CategoriaDocumento\Actualizar`

```php
final readonly class ActualizarCategoriaDto
{
    public function __construct(
        public ?string $nome,
        public ?string $slug,
        public ?TipoMovimento $tipo_movimento,
    ) {}

    public static function fromRequest(ActualizarCategoriaRequest $request): self
    {
        return new self(
            nome: $request->validated('nome'),
            slug: $request->validated('slug'),
            tipo_movimento: $request->has('tipo_movimento')
                ? TipoMovimento::from($request->validated('tipo_movimento'))
                : null,
        );
    }
}
```

---

## 2. Actions

### `ListarCategoriasAction`
**Namespace:** `App\Features\CategoriaDocumento\Listar`

```php
final class ListarCategoriasAction
{
    /** @return \Illuminate\Database\Eloquent\Collection<int, CategoriaDocumento> */
    public function handle(): \Illuminate\Database\Eloquent\Collection
    {
        return CategoriaDocumento::all();
    }
}
```

### `CriarCategoriaAction`
**Namespace:** `App\Features\CategoriaDocumento\Criar`

```php
final class CriarCategoriaAction
{
    public function handle(CriarCategoriaDto $dados): CategoriaDocumento
    {
        return CategoriaDocumento::create([
            'nome' => $dados->nome,
            'slug' => $dados->slug,
            'tipo_movimento' => $dados->tipo_movimento,
        ]);
    }
}
```

### `VerCategoriaAction`
**Namespace:** `App\Features\CategoriaDocumento\Ver`

```php
final class VerCategoriaAction
{
    public function handle(string $idCategoria): CategoriaDocumento
    {
        return CategoriaDocumento::findOrFail($idCategoria);
    }
}
```
> `findOrFail` lança `ModelNotFoundException` → convertido para 404 pelo exception handler.

### `ActualizarCategoriaAction`
**Namespace:** `App\Features\CategoriaDocumento\Actualizar`

```php
final class ActualizarCategoriaAction
{
    public function handle(string $idCategoria, ActualizarCategoriaDto $dados): CategoriaDocumento
    {
        $categoria = CategoriaDocumento::findOrFail($idCategoria);

        $campos = array_filter([
            'nome' => $dados->nome,
            'slug' => $dados->slug,
            'tipo_movimento' => $dados->tipo_movimento,
        ], fn (mixed $valor): bool => $valor !== null);

        $categoria->fill($campos)->save();

        return $categoria->fresh() ?? $categoria;
    }
}
```
> `array_filter` com `fn !== null` garante que só os campos enviados na request são actualizados.

### `EliminarCategoriaAction`
**Namespace:** `App\Features\CategoriaDocumento\Eliminar`

```php
final class EliminarCategoriaAction
{
    public function handle(string $idCategoria): void
    {
        $categoria = CategoriaDocumento::findOrFail($idCategoria);
        $categoria->delete();
    }
}
```

---

## 3. Controller

**Namespace:** `App\Features\CategoriaDocumento`
**Ficheiro:** `CategoriaDocumentoController.php`

```php
final class CategoriaDocumentoController extends Controller
{
    public function index(ListarCategoriasAction $accao): JsonResponse
    {
        $categorias = $accao->handle();
        return ApiResponse::devolverColeccao(
            CategoriaDocumentoResource::collection($categorias),
            ['total' => $categorias->count()]
        );
    }

    public function store(CriarCategoriaRequest $request, CriarCategoriaAction $accao): JsonResponse
    {
        $categoria = $accao->handle(CriarCategoriaDto::fromRequest($request));
        return ApiResponse::devolverCriado(new CategoriaDocumentoResource($categoria));
    }

    public function show(string $categorias_documento, VerCategoriaAction $accao): JsonResponse
    {
        $categoria = $accao->handle($categorias_documento);
        return ApiResponse::devolverSucesso(new CategoriaDocumentoResource($categoria));
    }

    public function update(ActualizarCategoriaRequest $request, string $categorias_documento, ActualizarCategoriaAction $accao): JsonResponse
    {
        $categoria = $accao->handle($categorias_documento, ActualizarCategoriaDto::fromRequest($request));
        return ApiResponse::devolverSucesso(new CategoriaDocumentoResource($categoria));
    }

    public function destroy(string $categorias_documento, EliminarCategoriaAction $accao): JsonResponse
    {
        $accao->handle($categorias_documento);
        return ApiResponse::devolverVazio();
    }
}
```

> O parâmetro de rota é `{categorias_documento}` (gerado por `apiResource`). A `ActualizarCategoriaRequest` usa `$this->route('categorias_documento')` — verificar e corrigir o valor actual (`'categoria'` → `'categorias_documento'`).

---

## 4. Rotas

**Ficheiro:** `routes/api.php`

```php
use App\Features\CategoriaDocumento\CategoriaDocumentoController;

Route::apiResource('categorias-documento', CategoriaDocumentoController::class);
```

Rotas geradas:

| Método | URI | Action do Controller |
|---|---|---|
| GET | `/api/categorias-documento` | `index` |
| POST | `/api/categorias-documento` | `store` |
| GET | `/api/categorias-documento/{categorias_documento}` | `show` |
| PUT/PATCH | `/api/categorias-documento/{categorias_documento}` | `update` |
| DELETE | `/api/categorias-documento/{categorias_documento}` | `destroy` |

---

## 5. Testes

**Directório:** `tests/Feature/Features/CategoriaDocumento/`

### `ListarCategoriasTest`
- `GET /api/categorias-documento` → 200
- Estrutura: `{ data: [...], meta: { total: N } }`
- Lista vazia → `data: []`, `meta.total: 0`

### `CriarCategoriaTest`
- `POST /api/categorias-documento` com dados válidos → 201 + estrutura do recurso
- Dados inválidos (slug duplicado, tipo_movimento inválido) → 422 + `errors`

### `VerCategoriaTest`
- `GET /api/categorias-documento/{id}` válido → 200 + estrutura do recurso
- ID inexistente → 404 + `{ status: 404, detail: "Recurso não encontrado." }`

### `ActualizarCategoriaTest`
- `PUT /api/categorias-documento/{id}` com dados parciais → 200 + recurso actualizado
- ID inexistente → 404
- Slug duplicado → 422

### `EliminarCategoriaTest`
- `DELETE /api/categorias-documento/{id}` → 204 sem body
- ID inexistente → 404

---

## 6. Invariantes

- Controller: zero lógica — só dispatch para Actions + ApiResponse
- Actions: método `handle()` único
- `strict_types=1` em todos os ficheiros
- Larastan nível 9 — zero erros
- 100% code coverage + 100% type coverage
