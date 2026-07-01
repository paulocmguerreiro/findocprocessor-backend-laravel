# Spec — Issue #71: Entidade — lógica layer (restaurar soft-deleted + ListarEntidades com inativas)

**Data:** 2026-07-01
**Issue:** #71
**Branch:** `feat/entidade-restaurar-logica`

---

## Contratos novos / alterados

### `EliminarEntidadeAction::handle(Entidade|string): void` — alterada

Padrão B via try/catch:

```php
DB::transaction(function () use ($entidade): void {
    try {
        $entidade->forceDelete();
    } catch (\Illuminate\Database\QueryException) {
        $entidade->delete();
    }
    $this->cache->invalidarCache(TagCache::Entidades);
});
```

`@throws`: `ModelNotFoundException<Entidade>`, `AuthorizationException`, `\Throwable`

### `Entidade` model — trait adicionado

```php
use HasFactory, HasUuids, RegistaActividade, SoftDeletes, FiltravelPorEstadoRegisto;
```

### `ListarEntidadesAction::handle()` — assinatura alterada

```
handle(int $porPagina, CampoOrdenacaoEntidades $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao, FiltroEstadoRegisto $filtroEstado): CursorPaginator<int, Entidade>
```

- Scope: `Entidade::filtrarPorEstadoRegisto($filtroEstado)->orderBy(...)->cursorPaginate($porPagina)`
- Cache key inclui `'estado' => $filtroEstado->value`

### `ListarEntidadesRequest::rules()` — campo adicionado

```php
'estado' => ['sometimes', 'string', Rule::in(array_column(FiltroEstadoRegisto::cases(), 'value'))],
```

Mensagem PT: `'estado.in' => 'O filtro de estado indicado não é válido.'`

### `EntidadePolicy::restore()` — método novo

```php
public function restore(User $utilizador, Entidade $entidade): bool
{
    return $utilizador->hasPermissionTo('entidades.eliminar');
}
```

### `RestaurarEntidadeAction` — nova

**Namespace:** `App\Features\Entidade\Restaurar`
**Assinatura:** `handle(Entidade|string $idEntidade): Entidade`

A Action aceita `Entidade|string` (padrão dual, igual a `EliminarEntidadeAction`): o modelo já resolvido via RMB no contexto HTTP, ou o UUID (string) em invocação programática. O ramo `string` resolve com `withTrashed()` porque o alvo do restore está soft-deleted.

```
1. is_string($idEntidade)
     ? Entidade::withTrashed()->findOrFail($idEntidade)   → @throws ModelNotFoundException
     : $idEntidade
2. Gate::authorize('restore', $entidade)                   → fora da transação
3. DB::transaction():
   a. $entidade->restore()
   b. cache->invalidarCache(TagCache::Entidades)
4. return $entidade
```

`@throws`: `ModelNotFoundException<Entidade>`, `AuthorizationException`, `\Throwable`

### `RestaurarEntidadeRequest` — nova

**Namespace:** `App\Features\Entidade\Restaurar`

O modelo é resolvido por Route Model Binding (a rota `/restaurar` usa `->withTrashed()`), pelo que `$this->route('entidade')` já devolve a `Entidade` ligada — igual a `EliminarEntidadeRequest`:

```php
public function authorize(): bool
{
    Gate::authorize('restore', $this->route('entidade'));
    return true;
}

public function rules(): array { return []; }
```

Sem `messages()` (sem campos a validar).

### `EntidadeController::restaurar()` — método novo

```php
public function restaurar(RestaurarEntidadeRequest $pedido, Entidade $entidade, RestaurarEntidadeAction $accao): JsonResponse
{
    return ApiResponse::devolverSucesso(new EntidadeResource($accao->handle($entidade)));
}
```

O parâmetro `Entidade $entidade` é resolvido por RMB (rota com `->withTrashed()`), consistente com os restantes métodos do controller.

### `EntidadeController::index()` — extracção de `estado`

```php
$filtroEstado = FiltroEstadoRegisto::from($parametrosValidados['estado'] ?? FiltroEstadoRegisto::SomenteAtivos->value);
```

Passado como 4.º argumento a `$accao->handle(...)`.

---

## Rotas

```php
// api.php — substituir linha existente:
Route::apiResource('entidades', EntidadeController::class)
    ->withTrashed(['show', 'update', 'destroy']);

// adicionar após o apiResource:
Route::patch('entidades/{entidade}/restaurar', [EntidadeController::class, 'restaurar'])
    ->withTrashed();
```

A rota `/restaurar` usa `->withTrashed()` para que o RMB resolva a entidade soft-deleted (o RMB implícito exclui trashed por omissão). Assim o controller e o FormRequest recebem o modelo já ligado, sem resolução manual. Isto só se aplica a modelos com `SoftDeletes`.

---

## Testes

### `EliminarEntidadeActionTest` — adicionar casos

```
describe('como admin'):
  it('elimina permanentemente quando sem documentos associados') → assertDatabaseMissing
  it('faz soft delete quando tem documentos associados')         → assertSoftDeleted
```

Os casos existentes (`assertSoftDeleted` simples sem documentos) são actualizados/substituídos.

### `EliminarEntidadeTest` — adicionar casos HTTP

```
describe('autenticado'):
  it('elimina permanentemente (sem docs) e devolve 204')  → assertDatabaseMissing + Activity::count 0 (forceDelete não dispara event)
  it('faz soft delete (com docs) e devolve 204')          → assertSoftDeleted + Activity event 'deleted'
```

> **Nota:** `forceDelete()` no SQLite com `foreign_key_constraints = true` lança `QueryException`
> quando há documentos com `id_fornecedor`/`id_cliente` a apontar para a entidade.

### `RestaurarEntidadeActionTest` — novo

```
describe('como admin'):
  it('restaura entidade inativa')               → assertNotSoftDeleted + entidade devolvida
  it('lança ModelNotFoundException se não existe')

describe('sem permissão'):
  it('lança AuthorizationException')

it('guest lança AuthorizationException')
```

### `RestaurarEntidadeTest` — novo

```
describe('autenticado'):
  it('restaura e devolve 200 com EntidadeResource')   → deleted_at null, Activity event 'restored'
  it('devolve 404 se UUID não existe')
  it('devolve 404 se entidade activa (não soft-deleted)')  — impossível: withTrashed resolve activas também; este caso é idempotente (restore no-op), devolve 200

describe('sem permissão'):
  it('devolve 403')

it('guest devolve 401')
```

### `ListarEntidadesActionTest` — adicionar casos

```
it('lista apenas activas por omissão (SomenteAtivos)')
it('lista activas e inativas com Todos')
it('lista apenas inativas com SomenteInativos')
```

### `ListarEntidadesTest` — adicionar casos HTTP

```
it('GET /entidades sem estado retorna apenas activas')
it('GET /entidades?estado=todos retorna activas e inativas')
it('GET /entidades?estado=somente_inativos retorna apenas inativas')
it('estado inválido devolve 422')
```

---

## Critérios de aceitação (mapeamento)

| CA | Componente | Verificação |
|---|---|---|
| CA-01 | `EliminarEntidadeAction` (sem docs) | `assertDatabaseMissing` |
| CA-01b | `EliminarEntidadeAction` (com docs) | `assertSoftDeleted` |
| CA-02 | Testes Eliminar | ambos os ramos presentes |
| CA-03 | `RestaurarEntidadeAction` | `withTrashed()->findOrFail()` |
| CA-04 | `RestaurarEntidadeAction` | `Gate::authorize` fora da transação |
| CA-05 | `RestaurarEntidadeAction` | `$entidade->restore()` dentro de `DB::transaction()` |
| CA-06 | `RestaurarEntidadeTest` | 200 + `EntidadeResource` |
| CA-07 | `EntidadePolicy` | `restore()` com `entidades.eliminar` |
| CA-08 | `RestaurarEntidadeRequest` | `Gate::authorize('restore', ...)` no `authorize()` |
| CA-09 | `ListarEntidadesAction` | `filtrarPorEstadoRegisto()` scope |
| CA-10 | `ListarEntidadesAction` | `estado` na chave de cache |
| CA-11 | `ListarEntidadesTest` | `?estado=todos` inclui inativas |
| CA-12 | `RestaurarEntidadeTest` | matriz 3 estados (200 / 403 / 401) |
| CA-13 | `composer test` | 100% coverage + type coverage |
