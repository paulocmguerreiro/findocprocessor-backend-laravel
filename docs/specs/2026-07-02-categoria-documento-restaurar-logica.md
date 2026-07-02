# Spec — Issue #72: CategoriaDocumento — lógica layer (restaurar + listar com inativas)

**Data:** 2026-07-02
**Issue:** #72
**Branch:** `feat/categoria-documento-restaurar-logica`
**Brief:** `docs/briefs/2026-07-02-categoria-documento-restaurar-logica.md`

---

## Pré-condições verificadas

- `CategoriaDocumento` já tem `SoftDeletes` + `@property-read ?Carbon $deleted_at` (Issue #70) ✅
- `CategoriaDocumentoResource` já expõe `deleted_at` (Issue #70) ✅
- `CategoriaDocumentoFactory::inativa()` já existe ✅
- FK `documentos.id_categoria` é `restrictOnDelete()` — Padrão B funciona ✅
- Testes de eliminar já usam `assertSoftDeleted` (Issue #70) ✅

---

## Contratos novos / alterados

### `CategoriaDocumento` model — trait adicionado

```php
use FiltravelPorEstadoRegisto, HasFactory, HasUuids, RegistaActividade, SoftDeletes;
```

Ordem alphabética dos traits; sem outra alteração ao modelo.

---

### `EliminarCategoriaAction::handle(CategoriaDocumento|string): void` — alterada

Substituir `$categoria->delete()` por Padrão B:

```php
DB::transaction(function () use ($categoria): void {
    try {
        $categoria->forceDelete();
    } catch (QueryException) {
        // forceDelete() deixa forceDeleting=true ao lançar; fresh() garante soft delete real.
        $categoria->fresh()?->delete();
    }
    $this->cache->invalidarCache(TagCache::CategoriasDocumento);
});
```

Imports a adicionar: `Illuminate\Database\QueryException`.

`@throws`: `ModelNotFoundException<CategoriaDocumento>`, `AuthorizationException`, `\Throwable`

Comportamento resultante:
- Sem documentos referenciados → `forceDelete()` (hard delete permanente)
- Com documentos referenciados → `delete()` via catch (soft delete — `deleted_at` preenchido)

---

### `ListarCategoriasAction::handle()` — assinatura alterada

**Antes:**
```
handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator<int, CategoriaDocumento>
```

**Depois:**
```
handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao, FiltroEstadoRegisto $filtroEstado): CursorPaginator<int, CategoriaDocumento>
```

- Scope: `CategoriaDocumento::filtrarPorEstadoRegisto($filtroEstado)->orderBy(...)->cursorPaginate($perPage)`
- Chave de cache: incluir `'estado' => $filtroEstado->value`
- `Gate::authorize()` permanece inalterado (`viewAny`)

---

### `ListarCategoriasRequest::rules()` — campo adicionado

```php
'estado' => ['sometimes', 'string', Rule::in(array_column(FiltroEstadoRegisto::cases(), 'value'))],
```

Mensagem PT a adicionar em `messages()`:
```php
'estado.in' => 'O filtro de estado indicado não é válido.',
```

Import a adicionar: `App\Shared\Enums\FiltroEstadoRegisto`.

---

### `CategoriaDocumentoController` — duas alterações

**`index()` — passar `$filtroEstado`:**
```php
$filtroEstado = FiltroEstadoRegisto::from($parametrosValidados['estado'] ?? FiltroEstadoRegisto::SomenteAtivos->value);
$categorias = $accao->handle($porPagina, $campoOrdenacao, $direcaoOrdenacao, $filtroEstado);
```

PHPDoc de `$parametrosValidados` actualizado:
```php
/** @var array{per_page?: string, sort?: string, direction?: string, estado?: string} $parametrosValidados */
```

**`restaurar()` — método novo:**
```php
public function restaurar(RestaurarCategoriaRequest $pedido, CategoriaDocumento $categorias_documento, RestaurarCategoriaAction $accao): JsonResponse
{
    $categoria = $accao->handle($categorias_documento);

    return ApiResponse::devolverSucesso(new CategoriaDocumentoResource($categoria));
}
```

---

### `CategoriaDocumentoPolicy` — método novo

```php
public function restore(User $utilizador, CategoriaDocumento $categoriaDocumento): bool
{
    return $utilizador->hasPermissionTo('categorias-documento.eliminar');
}
```

Reutiliza a permissão `eliminar` — quem pode inactivar pode reactivar. Sem nova migration de seed.

---

### `RestaurarCategoriaRequest` — nova

**Namespace:** `App\Features\CategoriaDocumento\Restaurar`
**Classe:** `final class RestaurarCategoriaRequest extends FormRequest`

```php
public function authorize(): bool
{
    Gate::authorize('restore', $this->route('categorias_documento'));
    return true;
}

public function rules(): array
{
    return [];
}
```

Parâmetro de rota: `categorias_documento` (confirmado via `php artisan route:list`).

---

### `RestaurarCategoriaAction` — nova

**Namespace:** `App\Features\CategoriaDocumento\Restaurar`
**Classe:** `final readonly class RestaurarCategoriaAction`

```
Assinatura: handle(CategoriaDocumento|string $idCategoria): CategoriaDocumento
```

Fluxo:
```
1. is_string($idCategoria)
     ? CategoriaDocumento::withTrashed()->findOrFail($idCategoria)   → @throws ModelNotFoundException
     : $idCategoria
2. Gate::authorize('restore', $categoria)   → fora da transação
3. DB::transaction():
   a. $categoria->restore()
   b. cache->invalidarCache(TagCache::CategoriasDocumento)
4. return $categoria
```

`@throws`: `ModelNotFoundException<CategoriaDocumento>`, `AuthorizationException`, `\Throwable`

Sem Log — `RegistaActividade` no modelo regista automaticamente o evento `restored`.

---

### Rotas — `routes/api.php`

**Antes:**
```php
Route::apiResource('categorias-documento', CategoriaDocumentoController::class);
```

**Depois:**
```php
Route::apiResource('categorias-documento', CategoriaDocumentoController::class)
    ->withTrashed(['show', 'update', 'destroy']);
Route::patch('categorias-documento/{categorias_documento}/restaurar', [CategoriaDocumentoController::class, 'restaurar'])
    ->withTrashed();
```

`withTrashed(['show', 'update', 'destroy'])` — permite RMB de registos inactivos nessas rotas.
`->withTrashed()` na rota `/restaurar` — o alvo do restauro está soft-deleted.

---

## Testes

### Padrão dual

| Tipo | Ficheiro | Acção |
|---|---|---|
| Unit | `tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php` | actualizar |
| Feature | `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php` | actualizar |
| Unit | `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php` | actualizar |
| Feature | `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php` | actualizar |
| Unit | `tests/Unit/Features/CategoriaDocumento/RestaurarCategoriaActionTest.php` | criar |
| Feature | `tests/Feature/Features/CategoriaDocumento/RestaurarCategoriaTest.php` | criar |

---

### `EliminarCategoriaActionTest` — casos a adicionar/actualizar

**Manter** os casos existentes mas actualizar:
- `assertSoftDeleted` já existe ✅ — verificar se cobre o branch FK (com documentos)

**Adicionar:**
```
it('elimina definitivamente quando categoria não tem documentos associados')
  → CategoriaDocumento::factory()->create() sem documentos
  → assertDatabaseMissing('categorias_documento', ['id' => $categoria->id])

it('faz soft delete quando categoria tem documentos associados')
  → CategoriaDocumento::factory()->create() + Documento::factory()->create(['id_categoria' => $categoria->id])
  → assertSoftDeleted('categorias_documento', ['id' => $categoria->id])
```

### `EliminarCategoriaTest` — casos a adicionar

```
it('elimina definitivamente quando sem documentos e devolve 204')
  → assertDatabaseMissing

it('faz soft delete quando tem documentos e devolve 204')
  → assertSoftDeleted
```

---

### `ListarCategoriasActionTest` — casos a adicionar

```
it('lista só activas por omissão')
it('lista só inactivas com FiltroEstadoRegisto::SomenteInativos')
it('lista todas com FiltroEstadoRegisto::Todos')
```

### `ListarCategoriasTest` — casos a adicionar

```
it('lista só activas por omissão (sem ?estado)')
it('lista só inactivas com ?estado=somente_inativos')
it('lista todas com ?estado=todos')
it('devolve 422 com estado inválido')
```

---

### `RestaurarCategoriaActionTest` — novo (Unit)

```
describe('como admin'):
  it('restaura categoria soft-deleted recebendo CategoriaDocumento directamente')
    → CategoriaDocumento::factory()->inativa()->create()
    → handle($categoria) → assertDatabaseHas com deleted_at null
  it('restaura categoria soft-deleted recebendo string UUID')
    → handle($categoria->id)
  it('faz rollback quando ocorre excepção durante restauro')

describe('sem permissão'):
  it('lança AuthorizationException')

it('exige utilizador autenticado (guest é rejeitado)')
```

### `RestaurarCategoriaTest` — novo (Feature)

```
describe('autenticado como admin'):
  it('restaura categoria inactiva e devolve 200 com resource')
    → CategoriaDocumento::factory()->inativa()->create()
    → PATCH /api/categorias-documento/{id}/restaurar
    → assertOk + assertJsonPath('data.deleted_at', null)
  it('devolve 404 quando categoria não existe')
  it('devolve 404 quando categoria está activa (não é soft-deleted)')
    → categoria sem deleted_at não existe em withTrashed() do ponto de vista do restaurar
    → na verdade existe — verificar: withTrashed() inclui activos também; o restauro de activo é no-op mas deve devolver 200

it('utilizador sem permissão recebe 403')
it('guest sem token recebe 401')
```

> **Nota sobre restaurar categoria activa:** `$categoria->restore()` numa categoria activa é seguro (no-op — `deleted_at` já é null). O endpoint devolve 200 com o resource. Não é caso de erro.

---

## Matriz de autorização — `restaurar`

| Estado | Resultado | HTTP |
|---|---|---|
| Admin com permissão `eliminar` | Restaura + devolve resource | 200 |
| Utilizador sem `eliminar` | Negado pela Policy | 403 |
| Guest (sem token) | Negado pelo middleware `auth:sanctum` | 401 |
| UUID não existe (nem soft-deleted) | `ModelNotFoundException` | 404 |

---

## Invariantes de implementação

1. `Gate::authorize()` **fora** da `DB::transaction()` (padrão dupla camada)
2. `CategoriaDocumento::withTrashed()->findOrFail()` no ramo string da `RestaurarCategoriaAction`
3. `fresh()?->delete()` no catch do Padrão B (não `$categoria->delete()` directo)
4. Chave de cache da listagem inclui `'estado'` para evitar cache poisoning
5. Rota `/restaurar` com `->withTrashed()` para RMB resolver soft-deleted

---

## Ficheiros afectados

| Ficheiro | Operação |
|---|---|
| `app/Models/CategoriaDocumento.php` | modificar — adicionar `FiltravelPorEstadoRegisto` |
| `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php` | modificar — Padrão B |
| `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php` | modificar — `FiltroEstadoRegisto` |
| `app/Features/CategoriaDocumento/Listar/ListarCategoriasRequest.php` | modificar — campo `estado` |
| `app/Features/CategoriaDocumento/CategoriaDocumentoController.php` | modificar — `restaurar()` + `index()` |
| `app/Policies/CategoriaDocumentoPolicy.php` | modificar — `restore()` |
| `app/Features/CategoriaDocumento/Restaurar/RestaurarCategoriaAction.php` | criar |
| `app/Features/CategoriaDocumento/Restaurar/RestaurarCategoriaRequest.php` | criar |
| `routes/api.php` | modificar — `withTrashed` + `/restaurar` |
| `tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php` | modificar |
| `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php` | modificar |
| `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php` | modificar |
| `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php` | modificar |
| `tests/Unit/Features/CategoriaDocumento/RestaurarCategoriaActionTest.php` | criar |
| `tests/Feature/Features/CategoriaDocumento/RestaurarCategoriaTest.php` | criar |
| `docs/system_spec/01-features/categoria-documento.md` | actualizar |
| `docs/system_spec/05-routes/categorias-documento.md` | actualizar |
| `docs/system_spec/00-index.md` | actualizar (Actions: 5→7, Rotas: 5→7) |
