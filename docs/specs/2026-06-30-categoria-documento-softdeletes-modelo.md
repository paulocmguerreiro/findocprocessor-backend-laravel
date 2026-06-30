# Spec — Issue #70
## CategoriaDocumento — model layer (SoftDeletes + migration deleted_at + FK restrictOnDelete + relação withTrashed)

**Data:** 2026-06-30
**Issue:** #70
**Brief:** `docs/briefs/2026-06-30-categoria-documento-softdeletes-modelo.md`

---

## Critérios de aceitação

| ID | Critério |
|---|---|
| CA-01 | Migration `add_softdeletes_to_categorias_documento_table` adiciona `deleted_at` nullable à tabela `categorias_documento` |
| CA-02 | Migration `update_fk_constraint_categoria_in_documentos` altera `id_categoria` de `nullOnDelete` para `restrictOnDelete`, com guard SQLite |
| CA-03 | `CategoriaDocumento` usa trait `SoftDeletes` + `@property-read ?Carbon $deleted_at` declarado no PHPDoc |
| CA-04 | `CategoriaDocumento::all()` exclui soft-deleted por defeito — comportamento automático do Eloquent |
| CA-05 | `Documento::categoria()` usa `->withTrashed()` — categorias inactivas carregam correctamente |
| CA-06 | `CategoriaDocumentoFactory` tem state `inativa()` com `deleted_at = now()` |
| CA-07 | `CategoriaDocumentoResource` expõe `deleted_at` como `?string` ISO 8601 (null quando activa) |
| CA-08 | `EliminarCategoriaTest`: `assertDatabaseMissing` → `assertSoftDeleted` |
| CA-09 | `CategoriaDocumentoTest`: `describe('SoftDeletes')` com 3 casos; `describe('Factory — states')` com caso `inativa` |
| CA-10 | `CategoriaDocumentoResourceTest`: teste para campo `deleted_at` (activa → null; inativa → ISO string) |
| CA-11 | Testes de `Documento` verificam que categoria inactiva ainda é carregada pela relação `categoria` |
| CA-12 | `composer test` verde — 100% coverage, 100% type-coverage, zero Larastan erros |

---

## Contrato de dados

### Tabela `categorias_documento` — após migração

| Coluna | Tipo | Nullable | Default |
|---|---|---|---|
| `deleted_at` | `timestamp` | sim | `null` |

### Tabela `documentos` — alteração FK

| Coluna | Constraint actual | Constraint nova |
|---|---|---|
| `id_categoria` | `nullOnDelete` | `restrictOnDelete` |

### `CategoriaDocumentoResource` — campos

| Campo | Tipo PHP | Serialização |
|---|---|---|
| `id` | `string` | directo |
| `nome` | `string` | directo |
| `slug` | `string` | directo |
| `tipo_movimento` | `TipoMovimento` | `->value` |
| `deleted_at` | `?Carbon` | `?->toIso8601String()` |

---

## Spec por componente

### Migration 1 — `add_softdeletes_to_categorias_documento_table`

```php
public function up(): void
{
    Schema::table('categorias_documento', fn (Blueprint $t) => $t->softDeletes());
}

public function down(): void
{
    Schema::table('categorias_documento', fn (Blueprint $t) => $t->dropSoftDeletes());
}
```

### Migration 2 — `update_fk_constraint_categoria_in_documentos`

```php
public function up(): void
{
    if (DB::getDriverName() === 'sqlite') {
        return;
    }

    Schema::table('documentos', function (Blueprint $t): void {
        $t->dropForeign('documentos_id_categoria_foreign');
        $t->foreignUuid('id_categoria')->nullable()->change()->constrained('categorias_documento')->restrictOnDelete();
    });
}

public function down(): void
{
    if (DB::getDriverName() === 'sqlite') {
        return;
    }

    Schema::table('documentos', function (Blueprint $t): void {
        $t->dropForeign('documentos_id_categoria_foreign');
        $t->foreignUuid('id_categoria')->nullable()->change()->constrained('categorias_documento')->nullOnDelete();
    });
}
```

### Model `CategoriaDocumento`

Adicionar trait `SoftDeletes` à lista `use` e `@property-read ?Carbon $deleted_at` ao bloco PHPDoc.

```php
/**
 * @property-read string       $id
 * @property-read string       $nome
 * @property-read string       $slug
 * @property-read TipoMovimento $tipo_movimento
 * @property-read Carbon       $created_at
 * @property-read Carbon       $updated_at
 * @property-read ?Carbon      $deleted_at
 */
// ...
use HasFactory, HasUuids, RegistaActividade, SoftDeletes;
```

### Model `Documento` — relação `categoria()`

```php
public function categoria(): BelongsTo
{
    return $this->belongsTo(CategoriaDocumento::class, 'id_categoria')->withTrashed();
}
```

### Factory `CategoriaDocumentoFactory` — state `inativa`

```php
public function inativa(): static
{
    return $this->state(['deleted_at' => now()]);
}
```

### Resource `CategoriaDocumentoResource`

```php
/**
 * @return array{id: string, nome: string, slug: string, tipo_movimento: string, deleted_at: ?string}
 */
public function toArray(Request $request): array
{
    return [
        'id'             => $this->id,
        'nome'           => $this->nome,
        'slug'           => $this->slug,
        'tipo_movimento' => $this->tipo_movimento->value,
        'deleted_at'     => $this->deleted_at?->toIso8601String(),
    ];
}
```

---

## Spec de testes

### `tests/Unit/Models/CategoriaDocumentoTest.php` — adições

```php
describe('SoftDeletes', function (): void {
    uses(RefreshDatabase::class);

    it('soft-deleta (deleted_at preenchido, registo permanece na BD)', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $categoria->delete();
        $this->assertSoftDeleted('categorias_documento', ['id' => $categoria->id]);
    });

    it('exclui categorias inactivas por defeito das queries', function (): void {
        CategoriaDocumento::factory()->inativa()->create();
        CategoriaDocumento::factory()->create();
        expect(CategoriaDocumento::count())->toBe(1);
    });

    it('state inativa define deleted_at', function (): void {
        $categoria = CategoriaDocumento::factory()->inativa()->make();
        expect($categoria->deleted_at)->not->toBeNull();
    });
});
```

Adicionar ao `describe('Factory — states')` existente:

```php
it('state inativa define deleted_at', function (): void {
    $categoria = CategoriaDocumento::factory()->inativa()->make();
    expect($categoria->deleted_at)->not->toBeNull();
});
```

### `tests/Unit/Features/CategoriaDocumento/CategoriaDocumentoResourceTest.php` — adições

```php
it('deleted_at é null quando activa', function (): void {
    $categoria = CategoriaDocumento::factory()->make(['deleted_at' => null]);
    $resultado = new CategoriaDocumentoResource($categoria)->toArray(new Request);
    expect($resultado['deleted_at'])->toBeNull();
});

it('deleted_at é ISO 8601 quando inactiva', function (): void {
    $categoria = CategoriaDocumento::factory()->inativa()->make();
    $resultado = new CategoriaDocumentoResource($categoria)->toArray(new Request);
    expect($resultado['deleted_at'])->toBeString()->not->toBeEmpty();
});
```

### `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php` — alteração

```php
// ANTES:
$this->assertDatabaseMissing('categorias_documento', ['id' => $categoria->id]);

// DEPOIS:
$this->assertSoftDeleted('categorias_documento', ['id' => $categoria->id]);
```

### Teste novo — relação `Documento::categoria()` com inactiva

Ficheiro: `tests/Unit/Models/DocumentoTest.php` (ou ficheiro de relações existente)

```php
describe('Relações', function (): void {
    uses(RefreshDatabase::class);

    it('categoria inactiva ainda é carregada via withTrashed', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $documento = Documento::factory()->processado()->create(['id_categoria' => $categoria->id]);
        $categoria->delete();

        $documento->refresh();

        expect($documento->categoria)->not->toBeNull()
            ->and($documento->categoria->id)->toBe($categoria->id);
    });
});
```

---

## System spec a actualizar (Fase 3a)

| Ficheiro | Alteração |
|---|---|
| `docs/system_spec/03-models/categoria-documento.md` | Adicionar `SoftDeletes`, `deleted_at`, state `inativa` |
| `docs/system_spec/03-models/documento.md` | Actualizar relação `categoria()` com `withTrashed()` + FK constraint |
| `openapi.yaml` | Adicionar `deleted_at` ao schema `CategoriaDocumentoResource` |
