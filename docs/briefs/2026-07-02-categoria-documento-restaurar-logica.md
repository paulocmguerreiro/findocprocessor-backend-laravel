# Brief — Issue #72: CategoriaDocumento — Lógica Restaurar + Listar com inativas + Testes

**Data:** 2026-07-02
**Branch:** `feat/categoria-documento-restaurar-logica`
**Issue:** #72 — feat(laravel): CategoriaDocumento — logica layer (restaurar soft-deleted + ListarCategorias com inativas + testes revistos)

---

## Contexto

A Issue #70 adicionou `SoftDeletes` ao modelo `CategoriaDocumento`. Esta issue cobre as consequências na camada de lógica — paralela à Issue #71 (Entidade), mas para o domínio `CategoriaDocumento`.

O modelo já tem `SoftDeletes`, `@property-read ?Carbon $deleted_at` e o `CategoriaDocumentoResource` já expõe `deleted_at`. Os testes de eliminar (`EliminarCategoriaTest`, `EliminarCategoriaActionTest`) já usam `assertSoftDeleted` — foram actualizados na Issue #70.

O que falta (camada de lógica):
- `CategoriaDocumento` não usa o trait `FiltravelPorEstadoRegisto` ainda
- `EliminarCategoriaAction` não implementa Padrão B
- `ListarCategoriasAction` não aceita filtro de estado
- Sem `RestaurarCategoriaAction` / `RestaurarCategoriaRequest`
- Sem `Policy::restore()`
- Rotas sem `->withTrashed(['show', 'update', 'destroy'])` e sem `/restaurar`

---

## Divergências detectadas — decisões de design

### D1: `EliminarCategoriaAction` — Padrão B vs "sem alteração"

A issue diz "sem alteração de código". Mas `soft-delete.md` (estabelecido na #71) exige Padrão B:

```php
try { $categoria->forceDelete(); }
catch (QueryException) { $categoria->fresh()?->delete(); }
```

O CA-01 da issue diz "registo existe com `deleted_at` preenchido" — implica soft delete sempre. Isso é inconsistente com Padrão B que faz hard delete quando não há referências.

**Decisão adoptada:** Implementar Padrão B, igual a `EliminarEntidadeAction` e `EliminarUtilizadorAction`. O CA-01 é reinterpretado como "registo inactivo quando tem documentos referenciados". Sem documentos → hard delete. Com documentos → soft delete. É o comportamento correcto: preserva integridade referencial sem lixo desnecessário.

### D2: `ListarCategoriasAction` — `bool $incluirInativas` vs `FiltroEstadoRegisto`

A issue propõe `bool $incluirInativas = false`. O padrão estabelecido (Issue #68, `soft-delete.md`) usa `FiltroEstadoRegisto` enum com o scope `filtrarPorEstadoRegisto()`.

**Decisão adoptada:** Usar `FiltroEstadoRegisto`, igual a `ListarEntidadesAction` e `ListarUtilizadoresAction`. Parâmetro query string: `?estado=somente_ativos|somente_inativos|todos`. O campo de cache inclui `estado` (não `incluir_inativas`). É mais expressivo e extensível.

---

## Estado actual do código (o que existe)

| Componente | Estado |
|---|---|
| `CategoriaDocumento` model — `SoftDeletes` | ✅ (Issue #70) |
| `CategoriaDocumento` model — `FiltravelPorEstadoRegisto` | ❌ falta |
| `CategoriaDocumentoFactory` — state `inativo` | ❓ verificar |
| `CategoriaDocumentoResource` — expõe `deleted_at` | ✅ (Issue #70) |
| `EliminarCategoriaAction` — Padrão B | ❌ falta (só `$categoria->delete()`) |
| `EliminarCategoriaTest` — `assertSoftDeleted` | ✅ (Issue #70) |
| `EliminarCategoriaActionTest` — `assertSoftDeleted` | ✅ (Issue #70) |
| `ListarCategoriasAction` — `FiltroEstadoRegisto` | ❌ falta |
| `ListarCategoriasRequest` — campo `estado` | ❌ falta |
| `RestaurarCategoriaAction` | ❌ criar |
| `RestaurarCategoriaRequest` | ❌ criar |
| `CategoriaDocumentoPolicy::restore()` | ❌ criar |
| Rotas — `withTrashed(['show', 'update', 'destroy'])` | ❌ falta |
| Rota `/categorias-documento/{id}/restaurar` | ❌ criar |

---

## Componentes a implementar

### 1. Model — adicionar `FiltravelPorEstadoRegisto`

```php
use App\Models\Concerns\FiltravelPorEstadoRegisto;

class CategoriaDocumento extends Model
{
    use FiltravelPorEstadoRegisto, HasFactory, HasUuids, RegistaActividade, SoftDeletes;
```

Verificar também se `CategoriaDocumentoFactory` tem state `inativo`.

### 2. `EliminarCategoriaAction` — Padrão B

```php
DB::transaction(function () use ($categoria): void {
    try {
        $categoria->forceDelete();
    } catch (\Illuminate\Database\QueryException) {
        $categoria->fresh()?->delete();
    }
    $this->cache->invalidarCache(TagCache::CategoriasDocumento);
});
```

Os testes de eliminar já usam `assertSoftDeleted` — mas precisam de branch adicional (sem documentos → hard delete, com documentos → soft delete) idêntico ao padrão da Entidade.

### 3. `ListarCategoriasAction` — `FiltroEstadoRegisto`

Nova assinatura:
```php
handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao, FiltroEstadoRegisto $filtroEstado): CursorPaginator
```

- Scope: `CategoriaDocumento::filtrarPorEstadoRegisto($filtroEstado)->orderBy(...)->cursorPaginate($perPage)`
- Cache: incluir `'estado' => $filtroEstado->value` na chave

### 4. `ListarCategoriasRequest` — campo `estado`

Adicionar a `rules()`:
```php
'estado' => ['sometimes', 'string', Rule::in(array_column(FiltroEstadoRegisto::cases(), 'value'))],
```

Adicionar mensagem PT: `'estado.in' => 'O estado indicado não é válido.'`

### 5. `CategoriaDocumentoController::index` — passar `$filtroEstado`

```php
$filtroEstado = FiltroEstadoRegisto::from($parametrosValidados['estado'] ?? FiltroEstadoRegisto::SomenteAtivos->value);
$categorias = $accao->handle($porPagina, $campoOrdenacao, $direcaoOrdenacao, $filtroEstado);
```

### 6. `CategoriaDocumentoPolicy::restore()`

```php
public function restore(User $utilizador, CategoriaDocumento $categoriaDocumento): bool
{
    return $utilizador->hasPermissionTo('categorias-documento.eliminar');
}
```

### 7. `RestaurarCategoriaRequest`

```php
final class RestaurarCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('restore', $this->route('categorias_documento'));
        return true;
    }
    public function rules(): array { return []; }
}
```

### 8. `RestaurarCategoriaAction`

```php
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

### 9. `CategoriaDocumentoController::restaurar()`

```php
public function restaurar(RestaurarCategoriaRequest $pedido, CategoriaDocumento $categorias_documento, RestaurarCategoriaAction $accao): JsonResponse
{
    $categoria = $accao->handle($categorias_documento);
    return ApiResponse::devolverSucesso(new CategoriaDocumentoResource($categoria));
}
```

### 10. Rotas

```php
Route::apiResource('categorias-documento', CategoriaDocumentoController::class)
    ->withTrashed(['show', 'update', 'destroy']);
Route::patch('categorias-documento/{categorias_documento}/restaurar', [CategoriaDocumentoController::class, 'restaurar'])
    ->withTrashed();
```

---

## Testes

### Padrão dual obrigatório

| Ficheiro Unit | Ficheiro Feature |
|---|---|
| `tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php` | `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php` |
| `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php` | `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php` |
| `tests/Unit/Features/CategoriaDocumento/RestaurarCategoriaActionTest.php` (novo) | `tests/Feature/Features/CategoriaDocumento/RestaurarCategoriaTest.php` (novo) |

### Casos de teste críticos

**Eliminar (actualizar):**
- Branch sem documentos → `assertDatabaseMissing` (hard delete)
- Branch com documentos → `assertSoftDeleted` (soft delete, FK constraint)
- Rollback em excepção

**Listar (actualizar):**
- `?estado=somente_ativos` (default) → só activas
- `?estado=somente_inativos` → só inactivas
- `?estado=todos` → ambas

**Restaurar (novo):**
- Admin: 200 + `CategoriaDocumentoResource` com `deleted_at: null`
- Sem permissão: 403
- Sem auth: 401
- UUID inexistente: 404
- RollBack em excepção

---

## Riscos identificados

- **FK constraint em `categorias_documento`**: `documentos.id_categoria` → `restrictOnDelete` deve estar activo para o Padrão B funcionar. Verificar na migration #70.
- **Cache poisoning**: se a chave de listagem não incluir `estado`, uma listagem com `?estado=todos` pode contaminar o cache de `?estado=somente_ativos`. A chave DEVE incluir `estado`.
- **`forceDeleting` flag não reposta**: usar sempre `fresh()` no catch do Padrão B (armadilha documentada em `soft-delete.md`).
- **Teste de eliminar com documentos**: precisamos de criar `Documento` factory com `id_categoria` para o branch FK do Padrão B.

---

## Questões em aberto

- Verificar se `CategoriaDocumentoFactory` tem state `inativo` (com `deleted_at`) — se não tiver, adicionar.
- Confirmar que a migration de #70 criou a FK `restrictOnDelete` em `documentos.id_categoria`.

---

## Ficheiros a criar / modificar

| Acção | Ficheiro |
|---|---|
| Modificar | `app/Models/CategoriaDocumento.php` — adicionar `FiltravelPorEstadoRegisto` |
| Modificar | `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php` — Padrão B |
| Modificar | `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php` — `FiltroEstadoRegisto` |
| Modificar | `app/Features/CategoriaDocumento/Listar/ListarCategoriasRequest.php` — campo `estado` |
| Modificar | `app/Features/CategoriaDocumento/CategoriaDocumentoController.php` — `restaurar()` + `index()` |
| Modificar | `app/Policies/CategoriaDocumentoPolicy.php` — `restore()` |
| Criar | `app/Features/CategoriaDocumento/Restaurar/RestaurarCategoriaAction.php` |
| Criar | `app/Features/CategoriaDocumento/Restaurar/RestaurarCategoriaRequest.php` |
| Modificar | `routes/api.php` — `withTrashed` + rota restaurar |
| Modificar | `tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php` |
| Modificar | `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php` |
| Modificar | `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php` |
| Modificar | `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php` |
| Criar | `tests/Unit/Features/CategoriaDocumento/RestaurarCategoriaActionTest.php` |
| Criar | `tests/Feature/Features/CategoriaDocumento/RestaurarCategoriaTest.php` |
| Modificar | `docs/system_spec/01-features/categoria-documento.md` |
| Modificar | `docs/system_spec/05-routes/categorias-documento.md` |
| Actualizar | `docs/system_spec/00-index.md` |

---

## SYSTEM_SPEC a actualizar

- `01-features/categoria-documento.md` — adicionar `RestaurarCategoriaAction`, actualizar `ListarCategoriasAction` (assinatura + parâmetro `estado`), actualizar Policy (método `restore()`), actualizar FormRequests (novos + `ListarCategoriasRequest`)
- `05-routes/categorias-documento.md` — adicionar rota `/restaurar` + `withTrashed`, actualizar query params de listagem

---

## Referências

- Issue #70 — SoftDeletes no modelo
- Issue #71 — Entidade restaurar lógica (padrão de referência)
- `docs/system_spec/02-shared/soft-delete.md` — Padrão B, `FiltravelPorEstadoRegisto`, `FiltroEstadoRegisto`, endpoint restaurar
- `docs/system_spec/02-shared/padroes-acoes.md` — autorização dupla camada
- `docs/system_spec/04-infra/transactions.md` — `DB::transaction()` obrigatório
