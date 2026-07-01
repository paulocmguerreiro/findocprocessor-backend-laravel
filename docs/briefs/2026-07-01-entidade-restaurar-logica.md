# Brief — Issue #71: Entidade — lógica layer (restaurar soft-deleted + ListarEntidades com inativas)

**Data:** 2026-07-01
**Issue:** #71
**Slug:** `entidade-restaurar-logica`
**Branch:** `feat/entidade-restaurar-logica`

---

## Contexto

A issue #69 introduziu `SoftDeletes` no modelo `Entidade` e as FKs de `documentos` passaram a `restrictOnDelete`. Esta issue implementa a camada de lógica com base no **Padrão B** definido em `docs/system_spec/02-shared/soft-delete.md` (commit 6fc5d30).

---

## O que já existe (não requer código novo)

| Componente | Estado |
|---|---|
| `FiltroEstadoRegisto` enum | ✅ já existe em `App\Shared\Enums` |
| `FiltravelPorEstadoRegisto` trait | ✅ já existe em `App\Models\Concerns` |
| `EntidadeResource` com `deleted_at` | ✅ já implementado (#69) |
| `EntidadeFactory::inativa()` state | ✅ já existe |
| FKs `restrictOnDelete` em `documentos` | ✅ já existe (migration #69) |
| Testes `EliminarEntidade*` com `assertSoftDeleted` | ✅ base está correcta (#69), mas falta o branch do hard delete |

---

## Escopo completo desta issue

### A — `EliminarEntidadeAction` (código + testes)

Implementar Padrão B com **try/catch** (decisão actualizada em #71 — system_spec e `EliminarUtilizadorAction` já actualizados nesta sessão):

```php
try {
    $entidade->forceDelete();
} catch (\Illuminate\Database\QueryException) {
    $entidade->delete();
}
```

A BD (`restrictOnDelete` em `documentos.id_fornecedor` e `documentos.id_cliente`) actua como salvaguarda automática — qualquer nova FK protege o pai sem alterar código da Action. Requer `foreign_key_constraints = true` (projecto já tem por omissão).

Testes obrigatórios para **ambos** os branches (sem refs → hard delete `assertDatabaseMissing`; com refs → soft delete `assertSoftDeleted`).

### B — `Entidade` model — adicionar `FiltravelPorEstadoRegisto`

O checklist do system_spec exige o trait no model. Não estava em #69.

### C — `ListarEntidadesAction` + `ListarEntidadesRequest`

Substituir assinatura: `bool $incluirInativas` → `FiltroEstadoRegisto $filtroEstado` (default `SomenteAtivos`).
Usar scope `->filtrarPorEstadoRegisto($filtroEstado)` (trait já existe).
Campo na cache key: `estado` (não `incluir_inativas`).
Request: campo `estado`, `Rule::in(FiltroEstadoRegisto)`, mensagem PT.

### D — `EntidadePolicy::restore()`

```php
public function restore(User $utilizador, Entidade $entidade): bool
{
    return $utilizador->hasPermissionTo('entidades.eliminar');
}
```

### E — `RestaurarEntidadeAction` (nova)

Assinatura: `handle(Entidade|string $idEntidade): Entidade` (padrão dual, igual a `EliminarEntidadeAction`)
- Ramo `string`: resolve com `Entidade::withTrashed()->findOrFail($idEntidade)`; ramo `Entidade`: usa o modelo directamente (já ligado via RMB)
- `Gate::authorize('restore', $entidade)` fora da transação
- Dentro de `DB::transaction()`: `$entidade->restore()` + `cache->invalidarCache(TagCache::Entidades)`
- `@throws ModelNotFoundException`, `AuthorizationException`, `\Throwable`

### F — `RestaurarEntidadeRequest` (novo)

A rota `/restaurar` usa `->withTrashed()`, pelo que o RMB resolve a entidade soft-deleted e `$this->route('entidade')` já devolve o modelo — igual a `EliminarEntidadeRequest`:

```php
public function authorize(): bool
{
    Gate::authorize('restore', $this->route('entidade'));
    return true;
}
```

### G — `EntidadeController::restaurar()` (método novo)

```php
public function restaurar(RestaurarEntidadeRequest $pedido, Entidade $entidade, RestaurarEntidadeAction $accao): JsonResponse
{
    return ApiResponse::devolverSucesso(new EntidadeResource($accao->handle($entidade)));
}
```

O parâmetro `Entidade $entidade` é resolvido por RMB, consistente com os restantes métodos do controller.

### H — `routes/api.php`

Duas alterações:
1. `Route::apiResource('entidades', ...)` → adicionar `->withTrashed(['show', 'update', 'destroy'])` (acesso a inactivos em show/update/destroy, igual ao padrão `utilizadores`)
2. `Route::patch('entidades/{entidade}/restaurar', ...)->withTrashed()` — nova rota; o `->withTrashed()` faz o RMB incluir soft-deleted. **Preferir sempre RMB**; só as Actions aceitam `Entidade|string` (modelo ou UUID). O `->withTrashed()` só se aplica a modelos com `SoftDeletes`.

### I — Testes

| Ficheiro | Acção |
|---|---|
| `EliminarEntidadeActionTest` | adicionar branch hard delete (sem docs) + soft delete (com docs) |
| `EliminarEntidadeTest` | idem (HTTP) |
| `RestaurarEntidadeActionTest` | criar — admin restaura, sem permissão 403, guest 401 |
| `RestaurarEntidadeTest` | criar — HTTP PATCH /restaurar, matriz 3 estados |
| `ListarEntidadesActionTest` | adicionar casos com `FiltroEstadoRegisto` |
| `ListarEntidadesTest` | adicionar casos HTTP com `?estado=` |

---

## Riscos identificados

**`RegistaActividade` em `restore`:** spatie/activitylog regista evento `restored` mesmo sem properties mutadas (o `dontSubmitEmptyLogs()` só suprime `updated` sem changes). Confirmar com `Activity::count() === 1` e `event === 'restored'` nos testes de feature.

**`RegistaActividade` em `forceDelete`:** o hard delete não dispara `deleted` — não há activity log entry. Confirmar nos testes que `Activity::count() === 0` no branch sem refs.

**`EntidadeController` índice — impacto na assinatura `index`:** o método extrai `sort`/`direction`; adicionar extracção de `estado` e passagem para Action.

---

## Fora de âmbito

- Hard delete definitivo por escolha explícita (`forceDelete` endpoint)
- Listagem exclusiva de inativas como endpoint separado
- Novos papéis ou permissões (reutiliza `entidades.eliminar`)
- Anonimização RGPD (User, issue #73)
