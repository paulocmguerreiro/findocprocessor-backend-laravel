# Plano — Issue #69: Entidade SoftDeletes (Model Layer)

**Data:** 2026-06-30
**Branch:** `feat/entidade-softdeletes-modelo`
**Issue:** #69

---

## Tarefas

### T1 — Migration: `softDeletes` em `entidades`

**Ficheiro:** `database/migrations/YYYY_MM_DD_HHMMSS_add_softdeletes_to_entidades_table.php`

- Criar migration via `php artisan make:migration add_softdeletes_to_entidades_table`
- `up()`: `Schema::table('entidades', fn (Blueprint $t) => $t->softDeletes())`
- `down()`: `Schema::table('entidades', fn (Blueprint $t) => $t->dropSoftDeletes())`

---

### T2 — Migration: FK constraints `documentos` (nullOnDelete → restrictOnDelete)

**Ficheiro:** `database/migrations/YYYY_MM_DD_HHMMSS_update_fk_constraints_entidades_in_documentos.php`

- Criar migration via `php artisan make:migration update_fk_constraints_entidades_in_documentos`
- `up()`:
  - `dropForeign('documentos_id_fornecedor_foreign')`
  - `dropForeign('documentos_id_cliente_foreign')`
  - Re-add ambas com `->nullable()->restrictOnDelete()`
- `down()`: inverso — `dropForeign` + re-add com `->nullable()->nullOnDelete()`

---

### T3 — Model `Entidade`: trait SoftDeletes + PHPDoc

**Ficheiro:** `app/Models/Entidade.php`

- Import `use Illuminate\Database\Eloquent\SoftDeletes;`
- Adicionar `SoftDeletes` ao bloco `use` dos traits
- Adicionar `@property-read ?Carbon $deleted_at` ao bloco PHPDoc

---

### T4 — Model `Documento`: relações `fornecedor()` e `cliente()` com `withTrashed()`

**Ficheiro:** `app/Models/Documento.php`

- `fornecedor()`: `->belongsTo(Entidade::class, 'id_fornecedor')->withTrashed()`
- `cliente()`: `->belongsTo(Entidade::class, 'id_cliente')->withTrashed()`

---

### T5 — Factory `EntidadeFactory`: state `inativa()`

**Ficheiro:** `database/factories/EntidadeFactory.php`

- Adicionar método `inativa(): static` com `->state(['deleted_at' => now()])`

---

### T6 — Resource `EntidadeResource`: campo `deleted_at`

**Ficheiro:** `app/Features/Entidade/EntidadeResource.php`

- Adicionar `'deleted_at' => $this->deleted_at?->toIso8601String()` ao array
- Actualizar PHPDoc do return type (7 campos)

---

### T7 — Testes unitários

**Ficheiros a alterar:**

- `tests/Unit/Models/EntidadeTest.php`
  - Adicionar secção `SoftDeletes` (3 testes: soft-deleta, exclui por defeito, state inativa)
- `tests/Unit/Features/Entidade/EntidadeResourceTest.php`
  - Actualizar contagem de campos (6 → 7)
  - Adicionar teste `deleted_at` null quando activa
  - Adicionar teste `deleted_at` ISO 8601 quando inactiva
- `tests/Unit/Features/Entidade/EliminarEntidadeActionTest.php`
  - `assertDatabaseMissing` → `assertSoftDeleted` nos dois testes de eliminação bem-sucedida
- `tests/Unit/Models/DocumentoTest.php`
  - Remover testes `nullOnDelete` para `id_fornecedor` e `id_cliente` (entidade)
  - Adicionar testes `withTrashed`: `fornecedor()` e `cliente()` carregam entidade inactiva

---

### T8 — Testes feature

**Ficheiros a alterar:**

- `tests/Feature/Features/Entidade/EliminarEntidadeTest.php`
  - `assertDatabaseMissing` → `assertSoftDeleted`

---

### T9 — Qualidade

```bash
composer lint
composer refactor
composer test
```

Corrigir todos os erros antes de finalizar.

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8 → T9
```

T1 e T2 são independentes entre si mas devem vir antes dos modelos (schema primeiro).
T3 antes de T5 (factory precisa do model com SoftDeletes para que `inativa()` faça sentido).
T7 e T8 após T3–T6 (testes dependem dos ficheiros de produção).

---

## Commits planeados

```
feat(migration): add deleted_at to entidades (softDeletes)
feat(migration): update FK constraints entidades in documentos (restrictOnDelete)
feat(model): add SoftDeletes to Entidade + deleted_at property-read
feat(model): Documento fornecedor/cliente relations use withTrashed
feat(factory): EntidadeFactory state inativa (deleted_at)
feat(resource): EntidadeResource exposes deleted_at
test: update Entidade and Documento tests for SoftDeletes behaviour
```
