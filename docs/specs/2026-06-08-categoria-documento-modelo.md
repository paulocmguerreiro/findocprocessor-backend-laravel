# Spec — Issue #1: CategoriaDocumento — Camada de Modelo

**Data:** 2026-06-08
**Issue:** #1
**Branch:** `feat/categoria-documento-modelo`

---

## 1. Migration — `categorias_documento`

**Ficheiro:** `database/migrations/YYYY_MM_DD_HHMMSS_create_categorias_documento_table.php`

```php
Schema::create('categorias_documento', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('nome', 255)->index();
    $table->string('slug', 255)->unique();
    $table->string('tipo_movimento', 50);
    $table->timestamps();
});
```

**Notas:**
- `id` é UUID — não usar `$table->id()` (cria bigint autoincrement)
- `nome`: `string(255)` com `->index()` — pesquisas por nome são expectáveis
- `slug`: `string(255)` com `->unique()` — índice único garante integridade
- `tipo_movimento`: `string(50)` na BD — SQLite não suporta `ENUM`; cast no Model garante type safety; 50 chars é suficiente para os valores (`'debito'`, `'credito'`, `'neutro'`)
- Todas as colunas são NOT NULL por omissão — sem `->nullable()`
- `timestamps()` cria `created_at` e `updated_at` automaticamente

---

## 2. Enum — `TipoMovimento`

**Ficheiro:** `app/Shared/Enums/TipoMovimento.php`

```php
<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum TipoMovimento: string
{
    case Debito  = 'debito';
    case Credito = 'credito';
    case Neutro  = 'neutro';
}
```

**Notas:**
- `BackedEnum` string — valor na BD é lowercase (`'debito'`, não `'Debito'`)
- Cases em TitleCase PT — per convenção CLAUDE.md
- Namespace `App\Shared\Enums` — partilhado entre features

---

## 3. Model — `CategoriaDocumento`

**Ficheiro:** `app/Models/CategoriaDocumento.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\TipoMovimento;
use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $nome
 * @property-read string $slug
 * @property-read TipoMovimento $tipo_movimento
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
#[Table('categorias_documento')]
#[Fillable(['nome', 'slug', 'tipo_movimento'])]
#[Casts(['tipo_movimento' => TipoMovimento::class])]
class CategoriaDocumento extends Model
{
    use HasFactory;
    use HasUuids;
}
```

**Notas:**
- `#[Table('categorias_documento')]` — nome da tabela como atributo PHP; nome PT plural não é inferido pelo Eloquent
- `#[Fillable([...])]` — substituí `protected $fillable` pelo atributo de classe (Laravel 13)
- `#[Casts([...])]` — substituí `casts()` pelo atributo de classe; os três (`#[Table]`, `#[Fillable]`, `#[Casts]`) são atributos PHP de classe-nível
- `HasUuids` gera UUIDv7 por omissão (ordenável lexicograficamente) — não é necessário override
- `@property-read` em todas as colunas — obrigatório para Larastan nível 9

---

## 4. Factory — `CategoriaDocumentoFactory`

**Ficheiro:** `database/factories/CategoriaDocumentoFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CategoriaDocumento;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CategoriaDocumento>
 */
class CategoriaDocumentoFactory extends Factory
{
    protected $model = CategoriaDocumento::class;

    public function definition(): array
    {
        $nome = $this->faker->words(2, true);

        return [
            'nome'           => $nome,
            'slug'           => Str::slug($nome),
            'tipo_movimento' => $this->faker->randomElement(TipoMovimento::cases()),
        ];
    }

    public function comMovimentoDebito(): static
    {
        return $this->state(['tipo_movimento' => TipoMovimento::Debito]);
    }

    public function comMovimentoCredito(): static
    {
        return $this->state(['tipo_movimento' => TipoMovimento::Credito]);
    }

    public function comMovimentoNeutro(): static
    {
        return $this->state(['tipo_movimento' => TipoMovimento::Neutro]);
    }
}
```

**Notas:**
- `definition()` usa `faker->randomElement(TipoMovimento::cases())` — cobre qualquer TipoMovimento por omissão
- Slug gerado a partir do `nome` com `Str::slug()` — consistente com o que o repositório fará futuramente
- States nomeados em PT per convenção (`comMovimentoDebito`, não `withDebitMovement`)

---

## 5. Testes — `CategoriaDocumentoTest`

**Ficheiro:** `tests/Unit/Models/CategoriaDocumentoTest.php`

### Grupos de testes

**Model:**
- `tem uuid como chave primária` — `assertInstanceOf(string, $model->id)` + verifica formato UUID
- `tem fillable correcto` — `$model->getFillable()` contém `nome`, `slug`, `tipo_movimento`
- `casta tipo_movimento para TipoMovimento enum` — criar com string `'debito'`, verificar `instanceof TipoMovimento`
- `tem timestamps` — `usesTimestamps()` é `true`

**Factory — base:**
- `factory cria instância válida` — `CategoriaDocumento::factory()->make()` passa validação de campos

**Factory — states:**
- `state comMovimentoDebito define tipo_movimento como Debito`
- `state comMovimentoCredito define tipo_movimento como Credito`
- `state comMovimentoNeutro define tipo_movimento como Neutro`

---

## 6. Localização dos ficheiros

| Ficheiro                                                              | Namespace / contexto        |
|-----------------------------------------------------------------------|-----------------------------|
| `database/migrations/..._create_categorias_documento_table.php`       | —                           |
| `app/Shared/Enums/TipoMovimento.php`                                  | `App\Shared\Enums`          |
| `app/Models/CategoriaDocumento.php`                                   | `App\Models`                |
| `database/factories/CategoriaDocumentoFactory.php`                    | `Database\Factories`        |
| `tests/Unit/Models/CategoriaDocumentoTest.php`                        | `Tests\Unit\Models`         |

---

## 7. Verificação de qualidade

Pipeline obrigatória antes de fechar a issue:

```bash
composer lint          # Pint
composer refactor      # Rector
composer test:types    # Larastan nível 9
composer test          # pipeline completa (coverage + type-coverage)
```
