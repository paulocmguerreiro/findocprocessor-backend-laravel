# System Spec — Shared: SoftDelete (Padrão B)

> Referência cruzada: `03-models/00-convencoes-models.md`, `02-shared/enums.md`

---

## Quando usar SoftDelete

SoftDelete **só faz sentido em tabelas pai/transversais** — tabelas que são referenciadas
por FK de outras tabelas. O objectivo é preservar a integridade referencial sem destruir
dados históricos quando o registo "pai" é eliminado.

**Usar SoftDelete:**
- `entidades` — referenciada por `documentos.id_fornecedor` e `documentos.id_cliente`
- `categorias_documento` — referenciada por `documentos.id_categoria`
- `users` — referenciada por `documentos.id_responsavel` e `etapas_documento.id_utilizador`

**Não usar SoftDelete:**
- Tabelas folha sem FKs apontando para elas (ex: `etapas_documento` — ninguém referencia as etapas)
- Registos de auditoria (append-only por natureza)
- Tabelas pivot / associativas

---

## Padrão B — hard delete com fallback para soft delete (try/catch)

O comportamento padrão de eliminação em todas as tabelas com SoftDelete: tenta
hard delete; se a BD rejeitar por violação de FK, faz soft delete (fallback).
A decisão é tomada pela própria BD via `restrictOnDelete` — o código apenas
reage ao `QueryException`:

```php
DB::transaction(function () use ($registo): void {
    try {
        $registo->forceDelete();           // tenta hard delete
    } catch (\Illuminate\Database\QueryException) {
        // forceDelete() deixa o modelo com forceDeleting=true ao lançar; uma
        // instância fresca garante que o fallback é um soft delete real.
        $registo->fresh()?->delete();      // FK constraint → fallback soft delete
    }
    $this->cache->invalidarCache(TagCache::Xxx);
});
```

> **Armadilha (#71):** o fallback **tem de usar uma instância fresca** (`fresh()`).
> Ao lançar, `forceDelete()` não repõe a flag interna `forceDeleting`, pelo que um
> `$registo->delete()` no `catch` sobre a **mesma** instância voltaria a fazer hard
> delete e relançaria a excepção — o soft delete nunca aconteceria (falha silenciosa
> em testes SQLite e erro 500 em prod/MySQL).

### Porquê try/catch e não pré-verificação

Uma abordagem alternativa seria pré-verificar com `Filho::where('id_pai', $id)->exists()`
antes de decidir. O problema: o método `estaReferenciado()` tem de ser actualizado
**manualmente** sempre que uma nova relação FK é adicionada ao modelo — é uma
dependência implícita fácil de esquecer. Com try/catch, a própria restrição
`restrictOnDelete` na BD actua como salvaguarda automática: qualquer FK nova que
proteja o pai dispara o catch sem necessidade de alterar o código da Action.

> **Requisito:** `foreign_key_constraints = true` (SQLite dev/testes) e FKs declaradas
> `restrictOnDelete` (MySQL prod). O projecto já tem `DB_FOREIGN_KEYS=true` por omissão.

### Comportamento resultante

| Situação | Resultado | Efeito |
|---|---|---|
| Sem referências | `forceDelete()` | Registo eliminado permanentemente |
| Com referências | `delete()` (catch) | Registo soft-deleted (`deleted_at` preenchido) |

### Invariante obrigatória

As FKs das tabelas filhas **devem** ser `restrictOnDelete` (nunca `nullOnDelete`,
nunca `cascadeOnDelete`) — sem esta restrição o `forceDelete()` teria sucesso mesmo
com referências, o catch nunca seria atingido e dados históricos seriam destruídos.

> **Actualizado (#71):** `Entidade` é o segundo modelo a usar Padrão B. `User` (#68)
> actualizado para try/catch na mesma revisão.

---

## Enum `FiltroEstadoRegisto`

Todos os endpoints de listagem de modelos com SoftDelete aceitam o parâmetro
`estado` com este enum:

```php
// app/Shared/Enums/FiltroEstadoRegisto.php
enum FiltroEstadoRegisto: string
{
    case Todos          = 'todos';
    case SomenteAtivos  = 'somente_ativos';
    case SomenteInativos = 'somente_inativos';
}
```

- Valor por omissão: `SomenteAtivos` (comportamento pre-SoftDelete — sem regressão)
- Valores na query string: `todos`, `somente_ativos`, `somente_inativos`
- Validação no `FormRequest`: `Rule::in(array_column(FiltroEstadoRegisto::cases(), 'value'))`

### Aplicação na Action — via trait `FiltravelPorEstadoRegisto`

A tradução enum → scope de SoftDeletes **não é repetida em cada Action**. Vive num
trait transversal `App\Models\Concerns\FiltravelPorEstadoRegisto`, usado por todos os
modelos com SoftDeletes (a par do trait `SoftDeletes`). Expõe o scope
`filtrarPorEstadoRegisto(FiltroEstadoRegisto $filtro)`:

```php
// app/Models/Concerns/FiltravelPorEstadoRegisto.php
public function scopeFiltrarPorEstadoRegisto(Builder $query, FiltroEstadoRegisto $filtro): void
{
    match ($filtro) {
        FiltroEstadoRegisto::SomenteAtivos   => $query->withoutTrashed(),
        FiltroEstadoRegisto::SomenteInativos => $query->onlyTrashed(),
        FiltroEstadoRegisto::Todos           => $query->withTrashed(),
    };
}
```

A Action de listagem apenas encadeia o scope:

```php
User::with('roles')
    ->filtrarPorEstadoRegisto($filtroEstado)
    ->orderBy($campo->value, $direcao->value)
    ->cursorPaginate($porPagina);
```

> Decisão (#68): preferir sempre o scope do trait a chamadas dispersas de
> `withTrashed()`/`onlyTrashed()`/`withoutTrashed()` no código de domínio.

---

## Acesso a `update`/`delete` em registos inactivos

O acesso às operações de **leitura individual, actualização e eliminação tem de contemplar
sempre todos os registos**, incluindo os já soft-deleted (caso contrário o route model
binding devolveria 404 para um registo inactivo, impedindo, por exemplo, abrir o seu
detalhe ou a sua eliminação definitiva).

O binding inclui os registos inactivos declarando-o na rota de recurso:

```php
Route::apiResource('<recurso>', <Recurso>Controller::class)
    ->withTrashed(['show', 'update', 'destroy']);
```

- `withTrashed()` sem argumentos cobre apenas `show`/`edit`/`update` — **`destroy` exige
  o array explícito**. Como não há `edit` numa API, fica `['show', 'update', 'destroy']`.
- **`show` é incluído por coerência com `index`:** se a listagem expõe inactivos via
  `?estado=somente_inativos|todos`, o detalhe desses registos tem de abrir (senão a lista
  mostra-os mas o GET individual devolvia 404).
- O `FormRequest` (`authorize()` via `$this->route('<recurso>')`) e a Action recebem o
  modelo já resolvido com o registo inactivo, mantendo a autorização dupla camada intacta.
- Listagem (`index`) mantém o default `SomenteAtivos`; quem quiser inactivos usa `?estado=`.

> Decisão (#68): `show`/`update`/`destroy` resolvem com registos inactivos incluídos;
> `index` permanece activo-por-omissão (filtra via `?estado=`).

---

## Endpoint `RestaurarAction`

Todos os modelos com SoftDelete têm um endpoint de restauro (reactivação):

| Método HTTP | Rota | Policy method |
|---|---|---|
| `PATCH` | `/api/<recurso>/{id}/restaurar` | `restore()` — reutiliza permissão `eliminar` |

### Resolução no endpoint — **preferir sempre Route Model Binding**

O RMB implícito exclui soft-deleted por defeito, mas o alvo do restauro é precisamente
um registo inactivo. A solução é marcar a **rota** `/restaurar` com `->withTrashed()` para
o binding incluir soft-deleted — assim o controller e o `FormRequest` recebem o modelo
já resolvido, sem resolução manual:

```php
Route::patch('<recurso>/{<recurso>}/restaurar', [<Recurso>Controller::class, 'restaurar'])
    ->withTrashed();
```

```php
// Controller — type-hint do modelo (consistente com show/update/destroy)
public function restaurar(Restaurar<Recurso>Request $pedido, <Recurso> $<recurso>, Restaurar<Recurso>Action $accao): JsonResponse

// FormRequest — reutiliza o modelo já ligado (igual a Eliminar<Recurso>Request)
Gate::authorize('restore', $this->route('<recurso>'));
```

> **Convenção (#71):** preferir **sempre** RMB nos controllers/FormRequests. **Só as Actions**
> aceitam `<Recurso>|string` (o modelo já ligado, ou o UUID em invocação programática) —
> o ramo `string` resolve com `Model::withTrashed()->findOrFail($id)`. O `->withTrashed()`
> na rota só se aplica a modelos com `SoftDeletes`.

### Policy `restore()`

```php
public function restore(User $utilizador, Model $registo): bool
{
    return $utilizador->hasPermissionTo('<recurso>.eliminar');
}
```

Reutiliza a permissão `eliminar` — quem pode inactivar pode reactivar.
Não são necessárias permissões novas.

---

## Relações em modelos filhos

Modelos filhos que referem um pai com SoftDelete devem usar `withTrashed()`
nas relações para que registos históricos carreguem correctamente:

```php
// Documento.php
public function fornecedor(): BelongsTo
{
    return $this->belongsTo(Entidade::class, 'id_fornecedor')->withTrashed();
}
```

Sem `withTrashed()`, documentos que referenciam entidades inactivas retornam
`null` na relação — perda silenciosa de informação.

---

## User — padrão adicional (RGPD) — **adiado (Issue #73)**

O modelo `User` deverá, no ramo soft delete (catch), **anonimizar os dados pessoais**
(`name`, `email`, `password`) — passo de conformidade RGPD.

```
try { forceDelete() }           → sem referências: registo eliminado permanentemente
catch (QueryException) {
    [anonimizar]                ← anonimização: #73
    delete()                    → com referências: soft-deleted
}
```

**Estado (#71):** `EliminarUtilizadorAction` actualizada para try/catch (Padrão B);
a **anonimização** do ramo catch fica **adiada para a Issue #73** (dívida técnica).

---

## Checklist de implementação

Para cada modelo com SoftDelete:

- [ ] Migration `add_softdeletes_to_<tabela>_table` — `$table->softDeletes()`
- [ ] Migration para FKs das tabelas filhas — `nullOnDelete` → `restrictOnDelete`
- [ ] Trait `SoftDeletes` + `FiltravelPorEstadoRegisto` no model + `@property-read ?Carbon $deleted_at`
- [ ] Factory state `inativo` com `deleted_at` preenchido
- [ ] Resource expõe `deleted_at` (null ou ISO 8601)
- [ ] `EliminarAction` — Padrão B (`forceDelete` + `QueryException` catch + `delete`)
- [ ] `RestaurarAction` + `RestaurarRequest` + Policy `restore()` + rota `PATCH /restaurar`
- [ ] `ListarAction` aceita `FiltroEstadoRegisto $filtroEstado` (default `SomenteAtivos`) via scope `filtrarPorEstadoRegisto()`
- [ ] Rota de recurso com `->withTrashed(['show', 'update', 'destroy'])` (acesso a inactivos)
- [ ] Relações nas tabelas filhas usam `withTrashed()`
- [ ] Testes: branch hard delete (sem refs) + branch soft delete (com refs) + restaurar
