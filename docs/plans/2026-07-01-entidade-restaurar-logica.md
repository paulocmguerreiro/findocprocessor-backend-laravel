# Plano — Issue #71: Entidade — lógica layer (restaurar soft-deleted + ListarEntidades com inativas)

**Data:** 2026-07-01
**Issue:** #71
**Branch:** `feat/entidade-restaurar-logica`

---

## Ordem de implementação

As tarefas seguem dependências: model primeiro (trait), depois Actions/Requests que o usam, controller, rotas e por fim testes.

---

### T1 — `Entidade` model: adicionar `FiltravelPorEstadoRegisto`

**Ficheiro:** `app/Models/Entidade.php`

- Adicionar `use FiltravelPorEstadoRegisto;` ao bloco `use` do model
- Adicionar import `App\Models\Concerns\FiltravelPorEstadoRegisto`

---

### T2 — `EliminarEntidadeAction`: Padrão B (try/catch)

**Ficheiro:** `app/Features/Entidade/Eliminar/EliminarEntidadeAction.php`

- Substituir `$entidade->delete()` simples pelo bloco try/catch:
  ```php
  try {
      $entidade->forceDelete();
  } catch (\Illuminate\Database\QueryException) {
      $entidade->delete();
  }
  ```
- Manter `Gate::authorize('delete', $entidade)` fora da transação
- `@throws` já inclui `\Throwable` — verificar e manter

---

### T3 — `EntidadePolicy`: método `restore()`

**Ficheiro:** `app/Policies/EntidadePolicy.php`

- Adicionar método `restore(User $utilizador, Entidade $entidade): bool`
- Retorna `$utilizador->hasPermissionTo('entidades.eliminar')`

---

### T4 — `ListarEntidadesRequest`: campo `estado`

**Ficheiro:** `app/Features/Entidade/Listar/ListarEntidadesRequest.php`

- Adicionar import `FiltroEstadoRegisto`
- Adicionar ao `rules()`: `'estado' => ['sometimes', 'string', Rule::in(array_column(FiltroEstadoRegisto::cases(), 'value'))]`
- Adicionar ao `messages()`: `'estado.in' => 'O filtro de estado indicado não é válido.'`
- Actualizar `@var array{...}` em `validated()` no controller (feito em T6)

---

### T5 — `ListarEntidadesAction`: parâmetro `FiltroEstadoRegisto`

**Ficheiro:** `app/Features/Entidade/Listar/ListarEntidadesAction.php`

- Adicionar `FiltroEstadoRegisto $filtroEstado` como 4.º parâmetro de `handle()` (default `FiltroEstadoRegisto::SomenteAtivos`)
- Substituir `Entidade::orderBy(...)` por `Entidade::filtrarPorEstadoRegisto($filtroEstado)->orderBy(...)`
- Adicionar `'estado' => $filtroEstado->value` à chave de cache
- Adicionar imports necessários

---

### T6 — `EntidadeController`: actualizar `index()` + novo `restaurar()`

**Ficheiro:** `app/Features/Entidade/EntidadeController.php`

**`index()`:**
- Actualizar `@var array{...}` para incluir `'estado'?: string`
- Extrair `$filtroEstado = FiltroEstadoRegisto::from($parametrosValidados['estado'] ?? FiltroEstadoRegisto::SomenteAtivos->value)`
- Passar `$filtroEstado` como 4.º argumento a `$accao->handle()`

**`restaurar()` (novo):**
```php
public function restaurar(RestaurarEntidadeRequest $pedido, string $entidade, RestaurarEntidadeAction $accao): JsonResponse
{
    return ApiResponse::devolverSucesso(new EntidadeResource($accao->handle($entidade)));
}
```
- Adicionar imports de `RestaurarEntidadeRequest`, `RestaurarEntidadeAction`

---

### T7 — `RestaurarEntidadeRequest` (novo)

**Ficheiro:** `app/Features/Entidade/Restaurar/RestaurarEntidadeRequest.php`

- `final class RestaurarEntidadeRequest extends FormRequest`
- `authorize()`: resolve `Entidade::withTrashed()->findOrFail($this->route('entidade'))` + `Gate::authorize('restore', $entidade)`
- `rules()`: `return []`
- Sem `messages()` (sem campos)

---

### T8 — `RestaurarEntidadeAction` (nova)

**Ficheiro:** `app/Features/Entidade/Restaurar/RestaurarEntidadeAction.php`

- `final readonly class RestaurarEntidadeAction`
- Construtor injeta `CacheServico $cache`
- `handle(string $idEntidade): Entidade`
  1. `Entidade::withTrashed()->findOrFail($idEntidade)`
  2. `Gate::authorize('restore', $entidade)` — fora da transação
  3. `DB::transaction()`: `$entidade->restore()` + `cache->invalidarCache(TagCache::Entidades)`
  4. `return $entidade`
- `@throws ModelNotFoundException<Entidade>`, `AuthorizationException`, `\Throwable`

---

### T9 — `routes/api.php`

**Ficheiro:** `routes/api.php`

- Alterar linha `Route::apiResource('entidades', ...)`:
  ```php
  Route::apiResource('entidades', EntidadeController::class)
      ->withTrashed(['show', 'update', 'destroy']);
  ```
- Adicionar após:
  ```php
  Route::patch('entidades/{entidade}/restaurar', [EntidadeController::class, 'restaurar']);
  ```

---

### T10 — Testes `EliminarEntidade*`

**Ficheiros:**
- `tests/Unit/Features/Entidade/EliminarEntidadeActionTest.php`
- `tests/Feature/Features/Entidade/EliminarEntidadeTest.php`

**Unit:**
- Adicionar `describe('sem documentos associados')`: entidade isolada → `assertDatabaseMissing`
- Adicionar `describe('com documentos associados')`: criar `Documento` com `id_fornecedor` ou `id_cliente` → `assertSoftDeleted`
- Manter/adaptar casos de autorização existentes

**Feature (HTTP):**
- Caso sem docs: `assertDatabaseMissing` (hard delete) + `Activity::count() === 0` (forceDelete não dispara audit)
- Caso com docs: `assertSoftDeleted` + `Activity::count() === 1, event === 'deleted'`

---

### T11 — Testes `RestaurarEntidade*` (novos)

**Ficheiros:**
- `tests/Unit/Features/Entidade/RestaurarEntidadeActionTest.php`
- `tests/Feature/Features/Entidade/RestaurarEntidadeTest.php`

**Unit (`RestaurarEntidadeActionTest`):**
```
describe('como admin'):
  it('restaura entidade inativa') → assertNotSoftDeleted, devolve Entidade
  it('lança ModelNotFoundException se UUID não existe')
describe('sem permissão'):
  it('lança AuthorizationException')
it('guest lança AuthorizationException')
```

**Feature (`RestaurarEntidadeTest`):**
```
describe('autenticado'):
  it('restaura entidade inativa e devolve 200 + EntidadeResource') → deleted_at null, Activity event 'restored'
  it('devolve 404 se UUID não existe')
describe('sem permissão'):
  it('devolve 403')
it('guest devolve 401')
```

---

### T12 — Testes `ListarEntidades*`

**Ficheiros:**
- `tests/Unit/Features/Entidade/ListarEntidadesActionTest.php`
- `tests/Feature/Features/Entidade/ListarEntidadesTest.php`

**Unit:** adicionar casos para `FiltroEstadoRegisto::Todos`, `SomenteAtivos`, `SomenteInativos`
**Feature:** adicionar `?estado=todos`, `?estado=somente_inativos`, `?estado=invalido` (422)

---

### T13 — `composer test` + correcções

Correr pipeline completa e corrigir até zero erros:
```bash
composer lint
composer refactor
composer test
```

---

## Sequência de commits sugerida

```
feat(entidade): Padrão B try/catch em EliminarEntidadeAction — Issue #71
feat(entidade): FiltravelPorEstadoRegisto + FiltroEstadoRegisto em ListarEntidadesAction — Issue #71
feat(entidade): RestaurarEntidadeAction + Request + Policy restore() — Issue #71
feat(entidade): restaurar() no controller + rotas withTrashed — Issue #71
test(entidade): testes Eliminar (Padrão B) + Restaurar + Listar (estado) — Issue #71
```

> Em SDD os commits são feitos a cada checkpoint ②. A sequência acima é orientadora — podem ser agrupados diferentemente conforme o progresso.

---

## Riscos e dependências

| Risco | Mitigação |
|---|---|
| `forceDelete()` em SQLite sem FK activa passa sem erro → catch nunca atingido | `foreign_key_constraints = true` por omissão no projecto; confirmar nos testes que o branch soft-delete é atingido |
| `RegistaActividade` não regista `forceDelete` → `Activity::count()` pode variar | Confirmar nos testes de feature (esperar 0 no hard delete, 1 no soft delete) |
| Larastan nível 9 — `Entidade::withTrashed()` pode exigir anotação adicional | Correr `composer test:types` após T8 e corrigir |
| `@var array{...}` no controller `index()` desactualizado | Actualizar em T6 antes de correr o Larastan |
