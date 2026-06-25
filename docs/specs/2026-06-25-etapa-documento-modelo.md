# Spec — Issue #56: EtapaDocumento — Camada de Modelo

**Data:** 2026-06-25 · **Issue:** #56 · **Branch:** `feat/etapa-documento-modelo`

> Decisão de Checkpoint A confirmada: `id_utilizador` é `bigint` FK → `users` (não uuid/`utilizadores`).

---

## 1. Migration — `etapas_documento`

`database/migrations/<timestamp>_create_etapas_documento_table.php`

```php
Schema::create('etapas_documento', function (Blueprint $table) {
    $table->uuid('id')->primary()->comment('Identificador unico UUID v7');

    $table->foreignUuid('id_documento')->constrained('documentos')
        ->cascadeOnDelete()->comment('FK para o documento; historico segue o documento');

    $table->string('estado', 50)->index()->comment('Etapa atingida; cast EstadoDocumento');
    $table->text('motivo')->nullable()->comment('Motivo/resposta/nota da etapa; pode ser sensivel');

    $table->foreignId('id_utilizador')->nullable()->constrained('users')
        ->nullOnDelete()->comment('FK para o utilizador; null = passo automatico (sistema)');

    $table->timestamp('created_at')->nullable()->comment('Data+hora da etapa');
});
```

- **Sem `$table->timestamps()`** — só `created_at` (append-only, CA-02).
- `id_documento` `cascadeOnDelete()` (CA-01); `id_utilizador` `nullOnDelete()` (CA-05).
- `estado` com índice simples (consultas por etapa).
- `down()`: `Schema::dropIfExists('etapas_documento')`.

---

## 2. Model — `app/Models/EtapaDocumento.php`

```php
declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\EstadoDocumento;
use Database\Factories\EtapaDocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read string $id_documento
 * @property-read EstadoDocumento $estado
 * @property-read ?string $motivo
 * @property-read ?int $id_utilizador
 * @property-read Carbon $created_at
 * @property-read Documento $documento
 * @property-read ?User $utilizador
 */
#[Table('etapas_documento')]
#[Fillable(['id_documento', 'estado', 'motivo', 'id_utilizador'])]
class EtapaDocumento extends Model
{
    /** @use HasFactory<EtapaDocumentoFactory> */
    use HasFactory;

    use HasUuids;

    /** Append-only: sem updated_at. */
    public const UPDATED_AT = null;

    /** @return array{estado: class-string<EstadoDocumento>} */
    #[\Override]
    protected function casts(): array
    {
        return ['estado' => EstadoDocumento::class];
    }

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'id_documento');
    }

    /** @return BelongsTo<User, $this> */
    public function utilizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_utilizador');
    }
}
```

- **Não usa `RegistaActividade`** (Decisão #2 do Brief — esta tabela é o histórico de domínio).
- `public const UPDATED_AT = null` — assinatura `?string` do framework (passa Larastan 9).
- Model não-`final` (convenção dos restantes Models).

---

## 3. Relação no `Documento` — `app/Models/Documento.php` (adição)

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @return HasMany<EtapaDocumento, $this> */
public function historico(): HasMany
{
    return $this->hasMany(EtapaDocumento::class, 'id_documento')->orderBy('created_at');
}
```

- Adicionar `@property-read \Illuminate\Database\Eloquent\Collection<int, EtapaDocumento> $historico`
  ao PHPDoc do `Documento`.
- Ordenação ascendente por `created_at` (CA-04).

---

## 4. Factory — `database/factories/EtapaDocumentoFactory.php`

```php
/**
 * @extends Factory<EtapaDocumento>
 */
class EtapaDocumentoFactory extends Factory
{
    #[\Override]
    protected $model = EtapaDocumento::class;

    /** @return array{id_documento: Factory<Documento>, estado: EstadoDocumento, motivo: null, id_utilizador: null} */
    public function definition(): array
    {
        return [
            'id_documento' => Documento::factory(),
            'estado' => EstadoDocumento::Pendente,
            'motivo' => null,
            'id_utilizador' => null,
        ];
    }

    public function processado(): static
    {
        return $this->state(['estado' => EstadoDocumento::Processado]);
    }

    public function erro(): static
    {
        return $this->state([
            'estado' => EstadoDocumento::Erro,
            'motivo' => $this->faker->sentence(),
        ]);
    }

    public function perigoso(): static
    {
        return $this->state([
            'estado' => EstadoDocumento::Perigoso,
            'motivo' => $this->faker->sentence(),
        ]);
    }

    /** Passo do utilizador (não automático). */
    public function manual(): static
    {
        return $this->state([
            'estado' => EstadoDocumento::Processado,
            'id_utilizador' => User::factory(),
        ]);
    }
}
```

| State | `estado` | Campos notáveis |
|---|---|---|
| base | `Pendente` | `id_utilizador = null`, `motivo = null` |
| `processado()` | `Processado` | — |
| `erro()` | `Erro` | `motivo` preenchido |
| `perigoso()` | `Perigoso` | `motivo` preenchido |
| `manual()` | `Processado` | `id_utilizador` definido |

---

## 5. Testes

### `tests/Unit/Models/EtapaDocumentoTest.php` (novo)

- **Model:** uuid PK (`getKeyType()==='string'`, `getIncrementing()===false`); fillable correcto;
  tabela `etapas_documento`.
- **Append-only:** `EtapaDocumento::UPDATED_AT` é `null`; após `create()`, `updated_at` não existe
  na BD / não é gerido (verificar que só `created_at` é preenchido).
- **Cast:** `estado` → instância de `EstadoDocumento`.
- **Relações:** `belongsTo documento` (instância + id); `belongsTo utilizador` (instância + id);
  `utilizador` é `null` quando `id_utilizador` é `null`.
- **`cascadeOnDelete`:** ao eliminar o `Documento`, as etapas são removidas.
- **`nullOnDelete`:** ao eliminar o `User`, `id_utilizador` fica `null` (etapa sobrevive).
- **Factory states** (dataset): cada state define o `estado` esperado; `erro`/`perigoso` têm
  `motivo` não-null; `manual` tem `id_utilizador` não-null; base tem `id_utilizador`/`motivo` null.

### `tests/Unit/Models/DocumentoTest.php` (adição)

- **`historico` ordenado:** criar várias etapas com `created_at` desordenado;
  `$documento->historico` devolve por ordem `created_at` ascendente; `hasMany` instâncias de
  `EtapaDocumento`.

> Todos os testes de relação/persistência usam `uses(RefreshDatabase::class)`.

---

## 6. SYSTEM_SPEC a actualizar (na Fase 3a)

- `docs/system_spec/03-models/etapa-documento.md` (novo)
- `docs/system_spec/03-models/documento.md` (relação `historico`)
- `docs/system_spec/00-index.md` (novo Model na tabela "Modelos Eloquent")

---

## 7. Mapa CA → implementação

| CA | Onde |
|---|---|
| CA-01 | Migration: `foreignUuid('id_documento')...cascadeOnDelete()` |
| CA-02 | Migration sem `timestamps()` (só `created_at`) + Model `const UPDATED_AT = null` |
| CA-03 | Model `casts()` → `estado => EstadoDocumento::class` |
| CA-04 | `Documento::historico()` `hasMany(...)->orderBy('created_at')` |
| CA-05 | Migration `foreignId('id_utilizador')->nullable()...nullOnDelete()` |
| CA-06 | `EtapaDocumentoFactory` + states |
| CA-07 | `composer test` verde no fim de cada tarefa |
