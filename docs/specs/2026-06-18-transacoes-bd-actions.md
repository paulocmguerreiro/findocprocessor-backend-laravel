# Spec — Issue #34: Transações de BD nas Actions de escrita

**Data:** 2026-06-18
**Issue:** #34
**Slug:** `transacoes-bd-actions`
**Brief:** `docs/briefs/2026-06-18-transacoes-bd-actions.md`

---

## Padrão obrigatório — DB::transaction() nas Actions de escrita

### Estrutura canónica

```php
public function handle(XxxDto $dados): Xxx
{
    Gate::authorize('create', Xxx::class);  // autorização — fora da transação

    return DB::transaction(fn() => Xxx::create([...]));  // persistência — dentro
}
```

Para Actions que lêem antes de escrever (`findOrFail` + `save`):

```php
public function handle(Xxx|string $idXxx, ActualizarXxxDto $dados): Xxx
{
    /** @var Xxx $xxx */
    $xxx = is_string($idXxx)
        ? Xxx::findOrFail($idXxx)
        : $idXxx;

    Gate::authorize('update', $xxx);  // autorização — fora da transação

    return DB::transaction(function () use ($xxx, $dados): Xxx {
        $xxx->fill([...])->save();
        $xxx->refresh();
        return $xxx;
    });
}
```

Para Actions de eliminação:

```php
public function handle(Xxx|string $idXxx): void
{
    /** @var Xxx $xxx */
    $xxx = is_string($idXxx)
        ? Xxx::findOrFail($idXxx)
        : $idXxx;

    Gate::authorize('delete', $xxx);  // autorização — fora da transação

    DB::transaction(fn() => $xxx->delete());
}
```

### PHPDoc @throws

`DB::transaction()` re-lança qualquer `\Throwable` automaticamente. Adicionar ao PHPDoc:

```php
/**
 * @throws AuthorizationException
 * @throws ModelNotFoundException
 * @throws \Throwable
 */
```

---

## Alterações por ficheiro

### `CriarCategoriaAction.php`

- Envolver `CategoriaDocumento::create([...])` em `DB::transaction(fn() => ...)`
- Adicionar `use Illuminate\Support\Facades\DB`
- Adicionar `@throws \Throwable` ao PHPDoc

### `ActualizarCategoriaAction.php`

- Envolver `fill()->save()` + `refresh()` em `DB::transaction(function () use (...): CategoriaDocumento { ... })`
- Adicionar `use Illuminate\Support\Facades\DB`
- Adicionar `@throws \Throwable` ao PHPDoc

### `EliminarCategoriaAction.php`

- Envolver `$categoria->delete()` em `DB::transaction(fn() => ...)`
- Adicionar `use Illuminate\Support\Facades\DB`
- Adicionar `@throws \Throwable` ao PHPDoc

### `CLAUDE.md`

Adicionar à secção "Padrões obrigatórios":

```
- `DB::transaction()` obrigatório em todas as Actions de escrita (criar, actualizar, eliminar) — `Gate::authorize()` fica fora da transação, a persistência fica dentro. `DB::transaction()` faz rollback e re-lança automaticamente qualquer `\Throwable`.
```

### `docs/system_spec/04-infra.md`

Adicionar secção "Transações de BD" a documentar o padrão e as Actions que o implementam.

---

## Testes a criar/alterar

### `tests/Unit/Features/CategoriaDocumento/CriarCategoriaActionTest.php` — novo

| Teste | O que verifica |
|---|---|
| `cria categoria com dados válidos` | Happy path — Action cria e devolve modelo |
| `faz rollback quando ocorre excepção após insert` | `Model::created()` hook lança; `assertDatabaseCount(..., 0)` |

### `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaActionTest.php` — adicionar

| Teste (novo) | O que verifica |
|---|---|
| `faz rollback quando ocorre excepção durante update` | `Model::saved()` hook lança; campos originais preservados na BD |

### `tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php` — adicionar

| Teste (novo) | O que verifica |
|---|---|
| `faz rollback quando ocorre excepção durante eliminação` | `Model::deleting()` hook lança após partial; registo permanece |

---

## O que NÃO muda

- Assinaturas dos `handle()` — sem alteração pública
- Comportamento normal (happy path) — transação transparente para o caller
- FormRequests e Controller — sem alterações
- DTOs e Resource — sem alterações
- `VerCategoriaAction` e `ListarCategoriasAction` — sem alterações (só leitura)

---

## Critérios de aceitação (mapeados da issue)

| CA | Como verificar |
|---|---|
| CA-01: Actions de escrita envolvem persistência em `DB::transaction()` | Code review + testes passam |
| CA-02: Excepções fazem rollback e são re-lançadas | Testes de rollback |
| CA-03: Padrão documentado no `CLAUDE.md` | Presença da linha na secção "Padrões obrigatórios" |
| CA-04: Testes verificam rollback em falha a meio | `CriarCategoriaActionTest`, adições a `Actualizar` e `Eliminar` |
| CA-05: Jobs dentro de transações usam `after_commit` | Nota no `CLAUDE.md` — sem Jobs neste scope |
