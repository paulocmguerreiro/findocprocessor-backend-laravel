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

## Padrão B — hard delete com fallback para soft delete (pré-verificação)

O comportamento padrão de eliminação em todas as tabelas com SoftDelete: hard
delete quando o registo **não** é referenciado; soft delete (fallback) quando é.
A decisão é tomada por **pré-verificação determinística** das tabelas filhas —
**não** por `try/catch` em torno de `forceDelete()`:

```php
DB::transaction(function () use ($registo): void {
    $referenciado = Filho::where('id_pai', $registo->id)->exists() /* || ... outras FKs */;

    $referenciado
        ? $registo->delete()        // soft delete — preserva referência/histórico
        : $registo->forceDelete();  // sem referências → hard delete

    $this->cache->invalidarCache(TagCache::Xxx);
});
```

### Porquê pré-verificação e não `try/catch forceDelete`

O `try/catch (QueryException)` em torno de `forceDelete()` **não é fiável**: no
SQLite (testes, com a transação aninhada do `RefreshDatabase`) a violação de FK
`RESTRICT` difere para o commit e escapa ao `catch` (mesmo `\Throwable`). No MySQL
falha no statement, mas a pré-verificação é determinística e **cross-driver**, e
testável nos dois ramos sem depender do momento da verificação de FK.

### Comportamento resultante

| Situação | Resultado | Efeito |
|---|---|---|
| Sem referências | `forceDelete()` | Registo eliminado permanentemente |
| Com referências | `delete()` | Registo soft-deleted (`deleted_at` preenchido) |

### Invariante obrigatória

As FKs das tabelas filhas **devem** ser `restrictOnDelete` (nunca `nullOnDelete`,
nunca `cascadeOnDelete`) — é a **salvaguarda ao nível da BD** que garante que um
hard delete acidental de um pai referenciado é bloqueado, mesmo que a
pré-verificação fique desactualizada quando uma nova FK é adicionada.

> **Implementado (#68):** `User` é o primeiro modelo a usar Padrão B completo —
> ver `EliminarUtilizadorAction` (`estaReferenciado()` sobre `documentos.id_responsavel`
> e `etapas_documento.id_utilizador`).

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

### Resolução no endpoint

O parâmetro de rota `{id}` é passado como `string` ao controller — **não** como model
type-hinted (route model binding exclui soft-deleted por defeito). A Action resolve
com `Model::withTrashed()->findOrFail($id)`.

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

O modelo `User` deverá, no ramo soft delete, **anonimizar os dados pessoais**
(`name`, `email`, `password`) — passo de conformidade RGPD.

```
1. Pré-verificar referências (estaReferenciado())
2. Referenciado → [anonimizar (name, email, password)] + delete() (soft)   ← anonimização: #73
3. Sem referências → forceDelete() (registo desaparece; sem dados a tratar)
```

**Estado (#68):** o Padrão B (hard/soft) está implementado em `EliminarUtilizadorAction`;
a **anonimização** do ramo soft delete fica **adiada para a Issue #73** (dívida técnica).

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
