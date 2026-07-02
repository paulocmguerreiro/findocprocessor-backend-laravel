# Plano — Issue #72: CategoriaDocumento — lógica layer (restaurar + listar com inativas)

**Data:** 2026-07-02
**Issue:** #72
**Branch:** `feat/categoria-documento-restaurar-logica`
**Spec:** `docs/specs/2026-07-02-categoria-documento-restaurar-logica.md`

---

## Ordem de implementação

Model → Policy → Actions/Requests → Controller → Rotas → Testes

---

### T1 — `CategoriaDocumento` model: adicionar `FiltravelPorEstadoRegisto`

**Ficheiro:** `app/Models/CategoriaDocumento.php`

- Adicionar import `App\Models\Concerns\FiltravelPorEstadoRegisto`
- Adicionar `FiltravelPorEstadoRegisto` ao bloco `use` (por ordem: `FiltravelPorEstadoRegisto, HasFactory, HasUuids, RegistaActividade, SoftDeletes`)

**Verificação:** `composer lint` — sem alterações de estilo.

---

### T2 — `EliminarCategoriaAction`: Padrão B (forceDelete + catch)

**Ficheiro:** `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php`

- Adicionar import `Illuminate\Database\QueryException`
- Substituir `$categoria->delete()` dentro da `DB::transaction()` por:
  ```php
  try {
      $categoria->forceDelete();
  } catch (QueryException) {
      // forceDelete() deixa forceDeleting=true ao lançar; fresh() garante soft delete real.
      $categoria->fresh()?->delete();
  }
  ```
- Manter `Gate::authorize('delete', $categoria)` fora da transação
- `@throws` já inclui `\Throwable` — manter

**Verificação:** `composer lint && composer refactor`

---

### T3 — `CategoriaDocumentoPolicy`: método `restore()`

**Ficheiro:** `app/Policies/CategoriaDocumentoPolicy.php`

- Adicionar após `delete()`:
  ```php
  public function restore(User $utilizador, CategoriaDocumento $categoriaDocumento): bool
  {
      return $utilizador->hasPermissionTo('categorias-documento.eliminar');
  }
  ```

---

### T4 — `ListarCategoriasRequest`: campo `estado`

**Ficheiro:** `app/Features/CategoriaDocumento/Listar/ListarCategoriasRequest.php`

- Adicionar import `App\Shared\Enums\FiltroEstadoRegisto`
- Adicionar a `rules()`:
  ```php
  'estado' => ['sometimes', 'string', Rule::in(array_column(FiltroEstadoRegisto::cases(), 'value'))],
  ```
- Adicionar a `messages()`:
  ```php
  'estado.in' => 'O filtro de estado indicado não é válido.',
  ```

---

### T5 — `ListarCategoriasAction`: parâmetro `FiltroEstadoRegisto`

**Ficheiro:** `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php`

- Adicionar import `App\Shared\Enums\FiltroEstadoRegisto`
- Adicionar `FiltroEstadoRegisto $filtroEstado` como 4.º parâmetro de `handle()`
- Substituir a query:
  ```php
  // antes
  fn (): CursorPaginator => CategoriaDocumento::orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)->cursorPaginate($perPage),
  // depois
  fn (): CursorPaginator => CategoriaDocumento::filtrarPorEstadoRegisto($filtroEstado)->orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)->cursorPaginate($perPage),
  ```
- Adicionar `'estado' => $filtroEstado->value` à array de `criarChave()` (entre `'direcao'` e `'por_pagina'`)

---

### T6 — `RestaurarCategoriaRequest` (novo)

**Ficheiro:** `app/Features/CategoriaDocumento/Restaurar/RestaurarCategoriaRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Restaurar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class RestaurarCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('restore', $this->route('categorias_documento'));

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
```

---

### T7 — `RestaurarCategoriaAction` (nova)

**Ficheiro:** `app/Features/CategoriaDocumento/Restaurar/RestaurarCategoriaAction.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Restaurar;

use App\Models\CategoriaDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class RestaurarCategoriaAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<CategoriaDocumento>
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(CategoriaDocumento|string $idCategoria): CategoriaDocumento
    {
        /** @var CategoriaDocumento $categoria */
        $categoria = is_string($idCategoria)
            ? CategoriaDocumento::withTrashed()->findOrFail($idCategoria)
            : $idCategoria;

        Gate::authorize('restore', $categoria);

        DB::transaction(function () use ($categoria): void {
            $categoria->restore();
            $this->cache->invalidarCache(TagCache::CategoriasDocumento);
        });

        return $categoria;
    }
}
```

---

### T8 — `CategoriaDocumentoController`: `index()` + `restaurar()`

**Ficheiro:** `app/Features/CategoriaDocumento/CategoriaDocumentoController.php`

**`index()` — actualizar:**
- Actualizar PHPDoc: `@var array{per_page?: string, sort?: string, direction?: string, estado?: string}`
- Adicionar extracção de `$filtroEstado`:
  ```php
  $filtroEstado = FiltroEstadoRegisto::from($parametrosValidados['estado'] ?? FiltroEstadoRegisto::SomenteAtivos->value);
  ```
- Passar `$filtroEstado` como 4.º argumento a `$accao->handle()`

**`restaurar()` — novo (após `destroy()`):**
```php
public function restaurar(RestaurarCategoriaRequest $pedido, CategoriaDocumento $categorias_documento, RestaurarCategoriaAction $accao): JsonResponse
{
    $categoria = $accao->handle($categorias_documento);

    return ApiResponse::devolverSucesso(new CategoriaDocumentoResource($categoria));
}
```

Adicionar imports: `FiltroEstadoRegisto`, `RestaurarCategoriaAction`, `RestaurarCategoriaRequest`.

---

### T9 — `routes/api.php`: `withTrashed` + rota `/restaurar`

**Ficheiro:** `routes/api.php`

```php
// antes
Route::apiResource('categorias-documento', CategoriaDocumentoController::class);

// depois
Route::apiResource('categorias-documento', CategoriaDocumentoController::class)
    ->withTrashed(['show', 'update', 'destroy']);
Route::patch('categorias-documento/{categorias_documento}/restaurar', [CategoriaDocumentoController::class, 'restaurar'])
    ->withTrashed();
```

**Verificação:** `php artisan route:list --path=categorias-documento` — confirmar 7 rotas (5 CRUD + `/restaurar`).

---

### T10 — Testes: `EliminarCategoriaActionTest` + `EliminarCategoriaTest`

**Ficheiro Unit:** `tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php`

Substituir casos de `assertSoftDeleted` existentes por dois branches do Padrão B:

- `it('elimina definitivamente quando categoria não tem documentos associados')` → `assertDatabaseMissing`
- `it('faz soft delete quando categoria tem documentos associados')` → `assertSoftDeleted` (criar `Documento::factory()->create(['id_categoria' => $categoria->id])`)
- `it('faz rollback quando ocorre excepção durante eliminação')` — manter (adaptar se necessário)
- `it('lança AuthorizationException quando utilizador não tem permissão')` — manter
- `it('exige utilizador autenticado')` — manter

**Ficheiro Feature:** `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php`

Substituir/adicionar:
- `it('elimina definitivamente sem documentos e devolve 204')` → `assertNoContent` + `assertDatabaseMissing`
- `it('faz soft delete com documentos e devolve 204')` → `assertNoContent` + `assertSoftDeleted`
- Manter casos 404, 403, 401

---

### T11 — Testes: `ListarCategoriasActionTest` + `ListarCategoriasTest`

**Ficheiro Unit:** `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php`

Adicionar parâmetro `FiltroEstadoRegisto` a todos os `handle()` existentes (default `SomenteAtivos`). Adicionar:

- `it('lista só categorias activas com FiltroEstadoRegisto::SomenteAtivos')`
- `it('lista só categorias inactivas com FiltroEstadoRegisto::SomenteInativos')`
- `it('lista todas as categorias com FiltroEstadoRegisto::Todos')`

**Ficheiro Feature:** `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php`

Adicionar:
- `it('lista só activas por omissão (sem ?estado)')`
- `it('lista só inactivas com ?estado=somente_inativos')`
- `it('lista todas com ?estado=todos')`
- `it('devolve 422 com estado inválido')`

---

### T12 — Testes: `RestaurarCategoriaActionTest` (novo, Unit)

**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/RestaurarCategoriaActionTest.php`

```
uses(RefreshDatabase::class)
beforeEach → Cache::tags(['categorias_documento'])->flush()

describe('como admin'):
  it('restaura categoria inactiva recebendo CategoriaDocumento directamente')
    → factory()->inativa()->create()
    → handle($categoria) → assertDatabaseHas('categorias_documento', ['id' => ..., 'deleted_at' => null])
  it('restaura categoria inactiva recebendo string UUID')
    → handle($categoria->id)
  it('faz rollback quando ocorre excepção durante restauro')
    → CategoriaDocumento::restoring(fn() => throw new RuntimeException(...))
    → assertSoftDeleted após excepção

describe('sem permissão'):
  it('lança AuthorizationException')

it('exige utilizador autenticado (guest é rejeitado)')
```

---

### T13 — Testes: `RestaurarCategoriaTest` (novo, Feature)

**Ficheiro:** `tests/Feature/Features/CategoriaDocumento/RestaurarCategoriaTest.php`

```
uses(RefreshDatabase::class)
beforeEach → Activity::query()->delete()

describe('autenticado como admin'):
  it('restaura categoria inactiva e devolve 200 com resource')
    → factory()->inativa()->create()
    → PATCH /api/categorias-documento/{id}/restaurar
    → assertOk + assertJsonPath('data.deleted_at', null)
    → assertDatabaseHas com deleted_at null
  it('devolve 404 quando categoria não existe (UUID inexistente)')
    → PATCH /api/categorias-documento/00000000-.../restaurar → assertNotFound

it('utilizador sem permissão recebe 403')
  → criarEAutenticarUtilizador()
  → factory()->inativa()->create()
  → assertForbidden

it('guest sem token recebe 401')
  → assertUnauthorized
```

---

### T14 — `composer test` — pipeline completa

Executar `composer test` e corrigir todos os erros antes de finalizar.

Verificar:
- `composer test:types` — PHPStan nível 9, zero erros
- `composer test:type-coverage` — 100% tipos declarados
- `composer test:coverage` — 100% cobertura
- `composer test:arch` — regras arquitecturais

---

## Commits previstos

```
T1–T3  → feat(categoria-documento): model FiltravelPorEstadoRegisto + EliminarAction Padrão B + Policy restore
T4–T5  → feat(categoria-documento): ListarAction e Request com FiltroEstadoRegisto
T6–T9  → feat(categoria-documento): RestaurarAction + Request + Controller + rotas
T10–T13 → test(categoria-documento): testes Eliminar Padrão B + Listar estado + Restaurar
T14    → chore(categoria-documento): composer test verde — Issue #72
```

---

## Dependências entre tarefas

```
T1 (model)
  └─ T5 (ListarAction usa filtrarPorEstadoRegisto do trait)

T3 (Policy restore)
  └─ T6 (RestaurarRequest usa authorize('restore', ...))
  └─ T7 (RestaurarAction usa Gate::authorize('restore', ...))

T6 + T7
  └─ T8 (Controller importa ambos)
  └─ T12 + T13 (testes invocam a Action)

T8 + T9 (Controller + Rotas)
  └─ T13 (Feature tests usam endpoint HTTP)

T2 (Padrão B)
  └─ T10 (testes cobrem os dois branches)

T4 + T5
  └─ T11 (testes cobrem os três estados)
```
