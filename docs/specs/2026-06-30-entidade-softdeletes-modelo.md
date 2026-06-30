# Spec — Issue #69: Entidade SoftDeletes (Model Layer)

**Data:** 2026-06-30
**Branch:** `feat/entidade-softdeletes-modelo`
**Issue:** #69

---

## Migrations

### Migration 1: `add_softdeletes_to_entidades_table`

```php
Schema::table('entidades', function (Blueprint $table): void {
    $table->softDeletes();
});
```

Rollback:
```php
Schema::table('entidades', function (Blueprint $table): void {
    $table->dropSoftDeletes();
});
```

### Migration 2: `update_fk_constraints_entidades_in_documentos`

Altera `id_fornecedor` e `id_cliente` de `nullOnDelete` para `restrictOnDelete`:

```php
Schema::table('documentos', function (Blueprint $table): void {
    $table->dropForeign('documentos_id_fornecedor_foreign');
    $table->dropForeign('documentos_id_cliente_foreign');

    $table->foreign('id_fornecedor')
        ->references('id')->on('entidades')
        ->nullable()
        ->restrictOnDelete();

    $table->foreign('id_cliente')
        ->references('id')->on('entidades')
        ->nullable()
        ->restrictOnDelete();
});
```

Rollback: restaurar `nullOnDelete` (inverso).

> **SQLite:** `dropForeign` é no-op; `restrictOnDelete` não é enforced em runtime. Aceitável — os testes cobrem o comportamento via factories.

---

## Model `Entidade`

**Alterações em `app/Models/Entidade.php`:**

1. Adicionar `use SoftDeletes;` ao corpo da classe
2. Adicionar `@property-read ?Carbon $deleted_at` ao bloco PHPDoc
3. Import: `use Illuminate\Database\Eloquent\SoftDeletes;`

```php
/**
 * @property-read string $id
 * @property-read string $nome
 * @property-read string $nif
 * @property-read bool   $e_cliente
 * @property-read bool   $e_fornecedor
 * @property-read bool   $e_empresa_aplicacao
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read ?Carbon $deleted_at
 */
class Entidade extends Model
{
    use HasFactory, HasUuids, RegistaActividade, SoftDeletes;
    // ...
}
```

---

## Model `Documento`

**Alterações em `app/Models/Documento.php`:**

Relações `fornecedor()` e `cliente()` passam a usar `->withTrashed()`:

```php
/** @return BelongsTo<Entidade, $this> */
public function fornecedor(): BelongsTo
{
    return $this->belongsTo(Entidade::class, 'id_fornecedor')->withTrashed();
}

/** @return BelongsTo<Entidade, $this> */
public function cliente(): BelongsTo
{
    return $this->belongsTo(Entidade::class, 'id_cliente')->withTrashed();
}
```

Sem outras alterações ao model.

---

## Factory `EntidadeFactory`

Adicionar state `inativa()` com `deleted_at` preenchido:

```php
public function inativa(): static
{
    return $this->state(['deleted_at' => now()]);
}
```

> `->state(['deleted_at' => now()])` apenas define o atributo — não chama `delete()`, logo não dispara eventos do `RegistaActividade`. `Entidade::withoutEvents()` não é necessário.

---

## Resource `EntidadeResource`

**Alterações em `app/Features/Entidade/EntidadeResource.php`:**

Adicionar campo `deleted_at` ao array de retorno:

```php
/** @return array{id: string, nome: string, nif: string, e_cliente: bool, e_fornecedor: bool, e_empresa_aplicacao: bool, deleted_at: ?string} */
public function toArray(Request $request): array
{
    return [
        'id'                  => $this->id,
        'nome'                => $this->nome,
        'nif'                 => $this->nif,
        'e_cliente'           => $this->e_cliente,
        'e_fornecedor'        => $this->e_fornecedor,
        'e_empresa_aplicacao' => $this->e_empresa_aplicacao,
        'deleted_at'          => $this->deleted_at?->toIso8601String(),
    ];
}
```

---

## Testes a actualizar

### `tests/Unit/Models/EntidadeTest.php`

Adicionar secção `SoftDeletes`:

```php
describe('SoftDeletes', function (): void {
    uses(RefreshDatabase::class);

    it('soft-deleta o registo (deleted_at preenchido, registo persiste na BD)', function (): void {
        $entidade = Entidade::factory()->create();

        $entidade->delete();

        expect($entidade->deleted_at)->not->toBeNull()
            ->and(Entidade::withTrashed()->find($entidade->id))->not->toBeNull();
    });

    it('Entidade::all() exclui soft-deleted por defeito', function (): void {
        Entidade::factory()->create();
        Entidade::factory()->inativa()->create();

        expect(Entidade::count())->toBe(1);
    });

    it('state inativa tem deleted_at preenchido', function (): void {
        $entidade = Entidade::factory()->inativa()->make();

        expect($entidade->deleted_at)->not->toBeNull();
    });
});
```

Actualizar teste de fillable para incluir ausência de `deleted_at` no fillable (SoftDeletes gere a coluna internamente, não via fillable).

### `tests/Unit/Features/Entidade/EntidadeResourceTest.php`

Actualizar teste existente para incluir `deleted_at`:

```php
it('retorna os 7 campos com os valores correctos', function (): void {
    // ...
    expect($resultado)
        ->toHaveKey('deleted_at', null); // activa → null
});

it('deleted_at é ISO 8601 quando inactiva', function (): void {
    $entidade = Entidade::factory()->inativa()->make();
    $resultado = new EntidadeResource($entidade)->toArray(new Request);

    expect($resultado['deleted_at'])->toBeString()->toMatch('/^\d{4}-\d{2}-\d{2}T/');
});
```

### `tests/Unit/Features/Entidade/EliminarEntidadeActionTest.php`

Substituir `assertDatabaseMissing` por `assertSoftDeleted`:

```php
it('soft-deleta quando recebe Entidade directamente', function (): void {
    $entidade = Entidade::factory()->create();

    app(EliminarEntidadeAction::class)->handle($entidade);

    $this->assertSoftDeleted('entidades', ['id' => $entidade->id]);
});

it('soft-deleta quando recebe string UUID', function (): void {
    $entidade = Entidade::factory()->create();

    app(EliminarEntidadeAction::class)->handle($entidade->id);

    $this->assertSoftDeleted('entidades', ['id' => $entidade->id]);
});
```

### `tests/Feature/Features/Entidade/EliminarEntidadeTest.php`

Substituir `assertDatabaseMissing` por `assertSoftDeleted`:

```php
it('soft-deleta entidade e devolve 204', function (): void {
    $entidade = Entidade::factory()->create();
    Activity::query()->delete();

    $this->deleteJson("/api/entidades/{$entidade->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('entidades', ['id' => $entidade->id]);

    expect(Activity::count())->toBe(1)
        ->and(Activity::query()->first()->event)->toBe('deleted');
});
```

### `tests/Unit/Models/DocumentoTest.php`

Remover/substituir testes de `nullOnDelete` para entidades (fornecedor/cliente) — após a alteração, soft delete não nulifica a FK:

```php
// REMOVER: 'coloca id_fornecedor a null quando a entidade é eliminada (nullOnDelete)'
// REMOVER: 'coloca id_cliente a null quando a entidade é eliminada (nullOnDelete)' (se existir)

// ADICIONAR: relação carrega entidade inactiva (withTrashed)
it('fornecedor() carrega entidade inactiva via withTrashed', function (): void {
    $fornecedor = Entidade::factory()->fornecedor()->create();
    $documento = Documento::factory()->create(['id_fornecedor' => $fornecedor->id]);

    $fornecedor->delete(); // soft delete

    expect($documento->fresh()->fornecedor)->toBeInstanceOf(Entidade::class)
        ->and($documento->fresh()->fornecedor->trashed())->toBeTrue();
});

it('cliente() carrega entidade inactiva via withTrashed', function (): void {
    $cliente = Entidade::factory()->cliente()->create();
    $documento = Documento::factory()->create(['id_cliente' => $cliente->id]);

    $cliente->delete(); // soft delete

    expect($documento->fresh()->cliente)->toBeInstanceOf(Entidade::class)
        ->and($documento->fresh()->cliente->trashed())->toBeTrue();
});
```

---

## Contrato OpenAPI

`openapi.yaml` — schema `EntidadeResponse`: adicionar campo `deleted_at` (nullable string, formato ISO 8601). Breaking change: não (campo novo, nullable).

A actualização do `openapi.yaml` é feita na Fase 3a (`/documenta-implementacao`).

---

## System Spec a actualizar (Fase 3a)

- `docs/system_spec/03-models/entidade.md` — colunas (deleted_at), traits (SoftDeletes), factory states (inativa), resource fields
- `docs/system_spec/03-models/documento.md` — relações fornecedor/cliente (withTrashed), FK constraints (restrictOnDelete)
