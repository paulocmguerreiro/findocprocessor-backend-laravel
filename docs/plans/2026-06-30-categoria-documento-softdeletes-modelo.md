# Plano — Issue #70
## CategoriaDocumento — model layer (SoftDeletes + migration deleted_at + FK restrictOnDelete + relação withTrashed)

**Data:** 2026-06-30
**Issue:** #70
**Spec:** `docs/specs/2026-06-30-categoria-documento-softdeletes-modelo.md`

---

## Tarefas

### T1 — Migration: `add_softdeletes_to_categorias_documento_table`
**Ficheiros:** `database/migrations/YYYY_MM_DD_HHMMSS_add_softdeletes_to_categorias_documento_table.php`
- `php artisan make:migration add_softdeletes_to_categorias_documento_table --no-interaction`
- `up()`: `Schema::table('categorias_documento', fn (Blueprint $t) => $t->softDeletes())`
- `down()`: `Schema::table('categorias_documento', fn (Blueprint $t) => $t->dropSoftDeletes())`

### T2 — Migration: `update_fk_constraint_categoria_in_documentos`
**Ficheiros:** `database/migrations/YYYY_MM_DD_HHMMSS_update_fk_constraint_categoria_in_documentos.php`
- `php artisan make:migration update_fk_constraint_categoria_in_documentos --no-interaction`
- Guard SQLite obrigatório (`if (DB::getDriverName() === 'sqlite') return;`)
- `up()`: drop `documentos_id_categoria_foreign` → `foreignUuid('id_categoria')->nullable()->change()->constrained('categorias_documento')->restrictOnDelete()`
- `down()`: idem com `nullOnDelete()`

### T3 — Model `CategoriaDocumento`: SoftDeletes + PHPDoc
**Ficheiro:** `app/Models/CategoriaDocumento.php`
- Adicionar `use Illuminate\Database\Eloquent\SoftDeletes;`
- Adicionar `SoftDeletes` ao bloco `use HasFactory, HasUuids, RegistaActividade`
- Adicionar `@property-read ?Carbon $deleted_at` ao PHPDoc

### T4 — Model `Documento`: relação `categoria()` com `withTrashed()`
**Ficheiro:** `app/Models/Documento.php`
- Alterar `categoria()`: adicionar `->withTrashed()` ao `belongsTo`
- Actualizar `@property-read ?CategoriaDocumento $categoria` (sem alteração de tipo — já correcto)
- Actualizar comentário na tabela de relações no PHPDoc (se existir)

### T5 — Factory: state `inativa()`
**Ficheiro:** `database/factories/CategoriaDocumentoFactory.php`
- Adicionar `public function inativa(): static { return $this->state(['deleted_at' => now()]); }`

### T6 — Resource: expor `deleted_at`
**Ficheiro:** `app/Features/CategoriaDocumento/CategoriaDocumentoResource.php`
- Adicionar `'deleted_at' => $this->deleted_at?->toIso8601String()` ao array
- Actualizar PHPDoc `@return` para incluir `deleted_at: ?string`

### T7 — Testes: `CategoriaDocumentoTest` — `describe('SoftDeletes')` + state `inativa`
**Ficheiro:** `tests/Unit/Models/CategoriaDocumentoTest.php`
- Adicionar `describe('SoftDeletes')` com 3 casos (soft-deleta, exclui por defeito, state inativa)
- Adicionar caso `inativa` ao `describe('Factory — states')` existente

### T8 — Testes: `CategoriaDocumentoResourceTest` — campo `deleted_at`
**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/CategoriaDocumentoResourceTest.php`
- Adicionar teste `deleted_at é null quando activa`
- Adicionar teste `deleted_at é ISO 8601 quando inactiva`
- Actualizar teste `retorna os 4 campos` → passa a ser `retorna os 5 campos`

### T9 — Testes: `EliminarCategoriaTest` — `assertSoftDeleted`
**Ficheiro:** `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php`
- Substituir `assertDatabaseMissing` por `assertSoftDeleted`

### T10 — Testes: relação `Documento::categoria()` com inactiva
**Ficheiro:** `tests/Unit/Models/DocumentoTest.php` (criar se não existir) ou ficheiro adequado
- Verificar se já existe ficheiro de testes de Model para Documento
- Adicionar `describe('Relações')` com caso `categoria inactiva ainda é carregada via withTrashed`

### T11 — Lint + Refactor + Test
- `composer lint` — Pint
- `composer refactor` — Rector
- `composer test` — pipeline completa; corrigir todos os erros antes de finalizar

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8 → T9 → T10 → T11
```

Sequência linear — cada passo depende dos anteriores (migrations antes de models, models antes de testes).

---

## Riscos e notas

- **Guard SQLite em T2** — obrigatório; sem ele `composer test` falha em SQLite com erro de `dropForeign`.
- **T9** — `assertDatabaseMissing` falha silenciosamente se o registo existir mas com `deleted_at` preenchido; trocar para `assertSoftDeleted` é obrigatório após SoftDeletes activo.
- **T10** — verificar se `tests/Unit/Models/DocumentoTest.php` existe antes de criar; adicionar ao ficheiro existente se possível.
- **T11** — `composer test` inclui Larastan nível 9; qualquer `@property-read` em falta ou tipo errado produz erro de types.
