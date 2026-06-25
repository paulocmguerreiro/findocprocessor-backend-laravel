# Spec — Issue #45: Documento — Camada de Modelo

**Data:** 2026-06-25
**Issue:** #45
**Branch:** `feat/documento-modelo`

> Decisões do Checkpoint A incorporadas: (1) `RegistaActividade` adicionado; (2) `#[UsePolicy]` incluído; (3) factory cobre os 7 estados.

---

## 1. Migration — `documentos`

**Ficheiro:** `database/migrations/YYYY_MM_DD_HHMMSS_create_documentos_table.php`

```php
Schema::create('documentos', function (Blueprint $table) {
    $table->uuid('id')->primary()->comment('Identificador unico UUID v7');
    $table->string('status', 50)->default('PENDENTE')->index()->comment('Estado de processamento do documento');

    $table->foreignUuid('id_fornecedor')->nullable()->constrained('entidades')->nullOnDelete()->comment('FK para a entidade fornecedora');
    $table->foreignUuid('id_cliente')->nullable()->constrained('entidades')->nullOnDelete()->comment('FK para a entidade cliente');
    $table->foreignUuid('id_categoria')->nullable()->constrained('categorias_documento')->nullOnDelete()->comment('FK para a categoria do documento');

    $table->decimal('valor', total: 15, places: 2)->nullable()->comment('Valor monetario do documento; >= 0');
    $table->date('data_documento')->nullable()->index()->comment('Data do documento');

    $table->string('nome_ficheiro_original', 500)->comment('Nome original do ficheiro no upload');
    $table->string('disco_storage', 50)->comment('Nome do disco Laravel onde reside o ficheiro');
    $table->string('nome_ficheiro_storage', 500)->comment('Nome do ficheiro no disco');
    $table->string('hash_sha256', 64)->unique()->comment('SHA-256 do conteudo do ficheiro; previne duplicados');

    $table->timestamps();
});
```

**Notas:**
- `id` UUID — nunca `$table->id()`.
- `status`: `string(50)`, default `'PENDENTE'`, **índice simples**. SQLite/MySQL não usam ENUM nativo — o cast no Model garante type-safety.
- 3 FKs com `foreignUuid(...)->nullable()->constrained(<tabela>)->nullOnDelete()`. `id_fornecedor`/`id_cliente` → `entidades`; `id_categoria` → `categorias_documento`. Nullable por design (desconhecidos em `Pendente`).
- `valor`: `decimal(15,2)` nullable. **Cast Eloquent `decimal:2` devolve `string`** — invariante `>= 0` é validada nos DTOs (PHP), não por CHECK (SQLite não suporta).
- `data_documento`: `date` nullable com **índice simples**.
- `hash_sha256`: `string(64)` **único** — `->unique()` cria índice único.
- `disco_storage`/`nome_ficheiro_storage`: NOT NULL (sempre consistentes com o `status`; a consistência fica a cargo da Action de transição na #57).
- `nome_ficheiro_original`/`nome_ficheiro_storage`: `string(500)`.

---

## 2. Enum — `EstadoDocumento`

**Ficheiro:** `app/Shared/Enums/EstadoDocumento.php`

```php
<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum EstadoDocumento: string
{
    case Pendente        = 'PENDENTE';
    case AguardaEnvio    = 'AGUARDA_ENVIO';
    case Enviado         = 'ENVIADO';
    case AguardaResposta = 'AGUARDA_RESPOSTA';
    case Processado      = 'PROCESSADO';
    case Erro            = 'ERRO';
    case Perigoso        = 'PERIGOSO';
}
```

**Notas:**
- BackedEnum string, **7 casos** (sem `Desconhecido`). Cases TitleCase PT, valores UPPER_SNAKE PT.
- Cria de raiz — **não** existe `DocumentStatus` no código. Substitui o placeholder EN documentado em `02-shared/enums.md` (actualizar na Fase 3a).

---

## 3. Interface — `ContratoEstadoDocumento`

**Ficheiro:** `app/Shared/States/ContratoEstadoDocumento.php`

```php
<?php

declare(strict_types=1);

namespace App\Shared\States;

use App\Shared\Enums\EstadoDocumento;

interface ContratoEstadoDocumento
{
    public function estado(): EstadoDocumento;

    public function id(): string;

    public function discoStorage(): string;

    public function nomeFicheiroStorage(): string;
}
```

**Notas:**
- Apenas os getters **comuns aos 7 estados** (`id`, `disco_storage`, `nome_ficheiro_storage`) + o discriminador `estado()`. Campos extra (`nome_ficheiro_original`, `hash_sha256`, e o conjunto completo de `Processado`) vivem nas classes concretas que os têm.

---

## 4. State objects — `app/Shared/States/`

7 classes `final readonly`, cada uma implementando `ContratoEstadoDocumento`. Construídas a partir do Model via um construtor estático `deDocumento(Documento $documento): self`. **Sem método `correct()`** — transições ficam para a #57.

### 4.1 Estados "parciais" — `DocumentoPendente`, `DocumentoAguardaEnvio`, `DocumentoEnviado`, `DocumentoAguardaResposta`

Expõem: `id`, `disco_storage`, `nome_ficheiro_storage`, `nome_ficheiro_original`, `hash_sha256`. Padrão (exemplo `DocumentoPendente`):

```php
<?php

declare(strict_types=1);

namespace App\Shared\States;

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

final readonly class DocumentoPendente implements ContratoEstadoDocumento
{
    public function __construct(
        private string $id,
        private string $discoStorage,
        private string $nomeFicheiroStorage,
        private string $nomeFicheiroOriginal,
        private string $hashSha256,
    ) {}

    public static function deDocumento(Documento $documento): self
    {
        return new self(
            id: $documento->id,
            discoStorage: $documento->disco_storage,
            nomeFicheiroStorage: $documento->nome_ficheiro_storage,
            nomeFicheiroOriginal: $documento->nome_ficheiro_original,
            hashSha256: $documento->hash_sha256,
        );
    }

    public function estado(): EstadoDocumento
    {
        return EstadoDocumento::Pendente;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function discoStorage(): string
    {
        return $this->discoStorage;
    }

    public function nomeFicheiroStorage(): string
    {
        return $this->nomeFicheiroStorage;
    }

    public function nomeFicheiroOriginal(): string
    {
        return $this->nomeFicheiroOriginal;
    }

    public function hashSha256(): string
    {
        return $this->hashSha256;
    }
}
```

> `DocumentoAguardaEnvio`, `DocumentoEnviado`, `DocumentoAguardaResposta` são idênticas, mudando apenas o retorno de `estado()`.

### 4.2 Estados mínimos — `DocumentoErro`, `DocumentoPerigoso`

Expõem só `id`, `disco_storage`, `nome_ficheiro_storage` (o motivo do erro/perigo vive no histórico `EtapaDocumento`, #56). Implementam apenas os 4 métodos da interface. Exemplo `DocumentoErro`:

```php
final readonly class DocumentoErro implements ContratoEstadoDocumento
{
    public function __construct(
        private string $id,
        private string $discoStorage,
        private string $nomeFicheiroStorage,
    ) {}

    public static function deDocumento(Documento $documento): self
    {
        return new self(
            id: $documento->id,
            discoStorage: $documento->disco_storage,
            nomeFicheiroStorage: $documento->nome_ficheiro_storage,
        );
    }

    public function estado(): EstadoDocumento
    {
        return EstadoDocumento::Erro;
    }

    // id(), discoStorage(), nomeFicheiroStorage() — iguais ao 4.1
}
```

### 4.3 Estado completo — `DocumentoProcessado`

Expõe **todos** os campos. Para os campos do domínio (preenchidos no registo manual), além dos comuns:

```php
final readonly class DocumentoProcessado implements ContratoEstadoDocumento
{
    public function __construct(
        private string $id,
        private string $discoStorage,
        private string $nomeFicheiroStorage,
        private string $nomeFicheiroOriginal,
        private string $hashSha256,
        private ?string $idFornecedor,
        private ?string $idCliente,
        private ?string $idCategoria,
        private ?string $valor,            // cast decimal:2 → string
        private ?\DateTimeInterface $dataDocumento,
    ) {}

    public static function deDocumento(Documento $documento): self { /* mapeia todos */ }

    public function estado(): EstadoDocumento
    {
        return EstadoDocumento::Processado;
    }

    // + getters para cada campo
}
```

**Notas:**
- `valor` mantém-se `?string` (espelha o cast `decimal:2` do Model). A conversão para `float` é responsabilidade exclusiva do `DocumentoResource`.
- `dataDocumento` tipado `?\DateTimeInterface` (Carbon implementa-o).

---

## 5. Model — `Documento`

**Ficheiro:** `app/Models/Documento.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use App\Policies\DocumentoPolicy;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\States\ContratoEstadoDocumento;
use App\Shared\States\DocumentoAguardaEnvio;
use App\Shared\States\DocumentoAguardaResposta;
use App\Shared\States\DocumentoEnviado;
use App\Shared\States\DocumentoErro;
use App\Shared\States\DocumentoPendente;
use App\Shared\States\DocumentoPerigoso;
use App\Shared\States\DocumentoProcessado;
use Database\Factories\DocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read EstadoDocumento $status
 * @property-read ?string $id_fornecedor
 * @property-read ?string $id_cliente
 * @property-read ?string $id_categoria
 * @property-read ?string $valor
 * @property-read ?Carbon $data_documento
 * @property-read string $nome_ficheiro_original
 * @property-read string $disco_storage
 * @property-read string $nome_ficheiro_storage
 * @property-read string $hash_sha256
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read ?Entidade $fornecedor
 * @property-read ?Entidade $cliente
 * @property-read ?CategoriaDocumento $categoria
 */
#[Table('documentos')]
#[Fillable([
    'status', 'id_fornecedor', 'id_cliente', 'id_categoria', 'valor',
    'data_documento', 'nome_ficheiro_original', 'disco_storage',
    'nome_ficheiro_storage', 'hash_sha256',
])]
#[UsePolicy(DocumentoPolicy::class)]
class Documento extends Model
{
    /** @use HasFactory<DocumentoFactory> */
    use HasFactory;
    use HasUuids;
    use RegistaActividade;

    /**
     * @return array{status: class-string<EstadoDocumento>, valor: string, data_documento: string}
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'status' => EstadoDocumento::class,
            'valor' => 'decimal:2',
            'data_documento' => 'date',
        ];
    }

    /**
     * Campos sensíveis excluídos do audit trail (RGPD / PII indirecta).
     *
     * @return list<string>
     */
    protected function atributosExcluidosDaActividade(): array
    {
        return ['hash_sha256', 'disco_storage', 'nome_ficheiro_storage'];
    }

    public function estado(): ContratoEstadoDocumento
    {
        return match ($this->status) {
            EstadoDocumento::Pendente => DocumentoPendente::deDocumento($this),
            EstadoDocumento::AguardaEnvio => DocumentoAguardaEnvio::deDocumento($this),
            EstadoDocumento::Enviado => DocumentoEnviado::deDocumento($this),
            EstadoDocumento::AguardaResposta => DocumentoAguardaResposta::deDocumento($this),
            EstadoDocumento::Processado => DocumentoProcessado::deDocumento($this),
            EstadoDocumento::Erro => DocumentoErro::deDocumento($this),
            EstadoDocumento::Perigoso => DocumentoPerigoso::deDocumento($this),
        };
    }

    /** @return BelongsTo<Entidade, $this> */
    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Entidade::class, 'id_fornecedor');
    }

    /** @return BelongsTo<Entidade, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Entidade::class, 'id_cliente');
    }

    /** @return BelongsTo<CategoriaDocumento, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaDocumento::class, 'id_categoria');
    }

    /** @param Builder<Documento> $query */
    public function scopeWhereEstado(Builder $query, EstadoDocumento $estado): void
    {
        $query->where('status', $estado);
    }

    /** @param Builder<Documento> $query */
    public function scopeWhereProcessado(Builder $query): void
    {
        $query->where('status', EstadoDocumento::Processado);
    }

    /** @param Builder<Documento> $query */
    public function scopeWherePendente(Builder $query): void
    {
        $query->where('status', EstadoDocumento::Pendente);
    }

    /** @param Builder<Documento> $query */
    public function scopeWherePerigoso(Builder $query): void
    {
        $query->where('status', EstadoDocumento::Perigoso);
    }

    /** @param Builder<Documento> $query */
    public function scopeWhereErro(Builder $query): void
    {
        $query->where('status', EstadoDocumento::Erro);
    }
}
```

**Notas:**
- `casts()` **método** (não `#[Casts]`) — coerente com `Entidade`/`CategoriaDocumento` no código real.
- `@property-read EstadoDocumento $status` — o cast transforma o valor BD no enum.
- `@property-read ?string $valor` — o cast `decimal:2` devolve `string`.
- `where('status', $estado)` — passar o enum directamente; o Eloquent serializa o `value`.
- `match($this->status)` **exaustivo** (sem `default`) — Larastan 9 valida cobertura dos 7 casos.
- `RegistaActividade` + `atributosExcluidosDaActividade()` (decisão Checkpoint A).
- Model **não** é `final` — coerente com o codebase.

---

## 6. Factory — `DocumentoFactory`

**Ficheiro:** `database/factories/DocumentoFactory.php`

```php
/**
 * @extends Factory<Documento>
 */
class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    /**
     * Estado base: documento Processado (registo manual — todos os campos).
     */
    public function definition(): array
    {
        $nomeOriginal = $this->faker->word().'.pdf';

        return [
            'status' => EstadoDocumento::Processado,
            'id_fornecedor' => Entidade::factory()->fornecedor(),
            'id_cliente' => Entidade::factory()->cliente(),
            'id_categoria' => CategoriaDocumento::factory(),
            'valor' => $this->faker->randomFloat(2, 0, 9999),
            'data_documento' => $this->faker->date(),
            'nome_ficheiro_original' => $nomeOriginal,
            'disco_storage' => 'processado',
            'nome_ficheiro_storage' => $this->faker->uuid().'.pdf',
            'hash_sha256' => hash('sha256', $this->faker->unique()->sha256()),
        ];
    }

    public function pendente(): static    { /* status Pendente; disco 'entrada'; FKs/valor/data null */ }
    public function aguardaEnvio(): static { /* status AguardaEnvio; disco 'entrada' */ }
    public function enviado(): static      { /* status Enviado; disco 'enviado'; parcial */ }
    public function aguardaResposta(): static { /* status AguardaResposta; disco 'enviado' */ }
    public function processado(): static   { /* status Processado; disco 'processado' (== base) */ }
    public function erro(): static         { /* status Erro; disco 'erro'; parcial */ }
    public function perigoso(): static     { /* status Perigoso; disco 'perigoso'; parcial */ }
}
```

**Mapeamento state → `disco_storage`:** `pendente`/`aguardaEnvio` → `entrada`; `enviado`/`aguardaResposta` → `enviado`; `processado` → `processado`; `erro` → `erro`; `perigoso` → `perigoso`.

**Notas:**
- Base = `processado()` (todos os campos preenchidos). Os states "parciais" repõem FKs/`valor`/`data_documento` a `null` e ajustam `status` + `disco_storage`.
- **7 states** (decisão Checkpoint A) — cobrem os 7 ramos do `match` em `estado()`.
- `hash_sha256` único garantido por `faker->unique()` + `hash('sha256', ...)` (64 chars hex).

---

## 7. Policy — `DocumentoPolicy` (stub)

**Ficheiro:** `app/Policies/DocumentoPolicy.php`

```php
final class DocumentoPolicy
{
    public function viewAny(User $utilizador): bool { return true; }
    public function view(User $utilizador, Documento $documento): bool { return true; }
    public function create(User $utilizador): bool { return true; }
    public function update(User $utilizador, Documento $documento): bool { return true; }
    public function delete(User $utilizador, Documento $documento): bool { return true; }
}
```

**Notas:**
- Stub temporário — todos `true`. Assinaturas iguais a `EntidadePolicy` para substituição directa por `hasPermissionTo(...)` na issue de autenticação.

---

## 8. DTOs — `app/Features/Documento/`

### 8.1 `CriarDocumentoManualDto`

**Ficheiro:** `app/Features/Documento/Criar/CriarDocumentoManualDto.php`

```php
final readonly class CriarDocumentoManualDto
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $idFornecedor,
        public string $idCliente,
        public string $idCategoria,
        public float $valor,
        public \DateTimeInterface $dataDocumento,
        public string $nomeFicheiroOriginal,
        public string $discoStorage,
        public string $nomeFicheiroStorage,
        public string $hashSha256,
    ) {
        if (trim($this->idFornecedor) === '') {
            throw new \InvalidArgumentException('idFornecedor não pode ser vazio.');
        }
        if (trim($this->idCliente) === '') {
            throw new \InvalidArgumentException('idCliente não pode ser vazio.');
        }
        if (trim($this->idCategoria) === '') {
            throw new \InvalidArgumentException('idCategoria não pode ser vazio.');
        }
        if ($this->valor < 0) {
            throw new \InvalidArgumentException('valor não pode ser negativo.');
        }
        if (trim($this->nomeFicheiroOriginal) === '') {
            throw new \InvalidArgumentException('nomeFicheiroOriginal não pode ser vazio.');
        }
        if (trim($this->discoStorage) === '') {
            throw new \InvalidArgumentException('discoStorage não pode ser vazio.');
        }
        if (trim($this->nomeFicheiroStorage) === '') {
            throw new \InvalidArgumentException('nomeFicheiroStorage não pode ser vazio.');
        }
        if (strlen($this->hashSha256) !== 64) {
            throw new \InvalidArgumentException('hashSha256 tem de ter exactamente 64 caracteres.');
        }
    }
}
```

**Notas:**
- `valor` aceita `0` (`< 0` rejeita; `0` passa).
- `hashSha256`: comprimento != 64 lança excepção (CA-11).
- **Sem `fromRequest()`** — adicionado na #57 (ver `padroes-dtos.md`).

### 8.2 `ActualizarDocumentoDto`

**Ficheiro:** `app/Features/Documento/Actualizar/ActualizarDocumentoDto.php`

Estrutura **idêntica** a `CriarDocumentoManualDto` (update completo — todos obrigatórios, mesmas invariantes). Sem `fromRequest()`.

---

## 9. Resource — `DocumentoResource`

**Ficheiro:** `app/Features/Documento/DocumentoResource.php`

```php
/** @mixin Documento */
final class DocumentoResource extends JsonResource
{
    /**
     * @return array{
     *     id: string, status: string, fornecedor: mixed, cliente: mixed, categoria: mixed,
     *     valor: float|null, data_documento: string|null, nome_ficheiro_original: string,
     *     hash_sha256: string, criado_em: string, actualizado_em: string
     * }
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'fornecedor' => EntidadeResource::make($this->whenLoaded('fornecedor')),
            'cliente' => EntidadeResource::make($this->whenLoaded('cliente')),
            'categoria' => CategoriaDocumentoResource::make($this->whenLoaded('categoria')),
            'valor' => $this->valor !== null ? (float) $this->valor : null,
            'data_documento' => $this->data_documento?->format('Y-m-d'),
            'nome_ficheiro_original' => $this->nome_ficheiro_original,
            'hash_sha256' => $this->hash_sha256,
            'criado_em' => $this->created_at->toISOString(),
            'actualizado_em' => $this->updated_at->toISOString(),
        ];
    }
}
```

**Notas:**
- `status` → `$this->status->value` (string UPPER_SNAKE).
- `valor` → conversão explícita `string → float` (cast `decimal:2` devolve string).
- Relações via `whenLoaded()` + Resources existentes (`EntidadeResource`, `CategoriaDocumentoResource`).
- **Não** expõe `disco_storage` nem `nome_ficheiro_storage` (detalhes internos / PII indirecta).

---

## 10. Config — `config/filesystems.php`

Adicionar 5 discos `local` ao array `disks`:

```php
'entrada'    => ['driver' => 'local', 'root' => storage_path('app/entrada'),    'throw' => false],
'enviado'    => ['driver' => 'local', 'root' => storage_path('app/enviado'),    'throw' => false],
'processado' => ['driver' => 'local', 'root' => storage_path('app/processado'), 'throw' => false],
'erro'       => ['driver' => 'local', 'root' => storage_path('app/erro'),       'throw' => false],
'perigoso'   => ['driver' => 'local', 'root' => storage_path('app/perigoso'),   'throw' => false],
```

**Notas:**
- 5 discos PT (CA-16). Nomes correspondem ao `disco_storage` gravado na BD.
- Mantêm os `local`/`public`/`s3` existentes intactos.

---

## 11. Testes — `tests/Unit/`

> Padrão: model layer testa-se em `tests/Unit/`. Sem testes HTTP (não há endpoints nesta issue).

| Ficheiro | Cobre |
|---|---|
| `tests/Unit/Models/DocumentoTest.php` | UUID PK; fillable; casts (`status`→enum, `valor`→string decimal, `data`→Carbon); 3 relações `BelongsTo`; `nullOnDelete`; 5 scopes; `estado()` para os **7** casos retorna a classe correcta; audit exclui campos sensíveis |
| `tests/Unit/States/EstadoDocumentoStatesTest.php` | Cada um dos 7 state objects: getters comuns + getters específicos; `estado()` devolve o enum certo; `final readonly` |
| `tests/Unit/Policies/DocumentoPolicyTest.php` | Os 5 métodos do stub devolvem `true` |
| `tests/Unit/Features/Documento/CriarDocumentoManualDtoTest.php` | Happy path (incl. `valor = 0`); cada `\InvalidArgumentException` do construtor; `hashSha256` != 64 |
| `tests/Unit/Features/Documento/ActualizarDocumentoDtoTest.php` | Idem (update completo) |
| `tests/Unit/Features/Documento/DocumentoResourceTest.php` | Campos presentes; relações ausentes (`whenLoaded`) vs presentes; tipos correctos; `valor` é `float`; omite `disco_storage`/`nome_ficheiro_storage` |
| `tests/Unit/Factories/DocumentoFactoryTest.php` *(ou dentro de DocumentoTest)* | 7 states produzem instâncias válidas com `disco_storage` correcto |

**Cobertura:** exercitar os 7 ramos do `match` em `estado()` e os 7 states é necessário para 100% coverage + type coverage.

---

## 12. Localização dos ficheiros

| Ficheiro | Namespace / contexto |
|---|---|
| `database/migrations/..._create_documentos_table.php` | — |
| `app/Shared/Enums/EstadoDocumento.php` | `App\Shared\Enums` |
| `app/Shared/States/ContratoEstadoDocumento.php` | `App\Shared\States` |
| `app/Shared/States/Documento{Pendente,AguardaEnvio,Enviado,AguardaResposta,Processado,Erro,Perigoso}.php` | `App\Shared\States` |
| `app/Models/Documento.php` | `App\Models` |
| `database/factories/DocumentoFactory.php` | `Database\Factories` |
| `app/Policies/DocumentoPolicy.php` | `App\Policies` |
| `app/Features/Documento/Criar/CriarDocumentoManualDto.php` | `App\Features\Documento\Criar` |
| `app/Features/Documento/Actualizar/ActualizarDocumentoDto.php` | `App\Features\Documento\Actualizar` |
| `app/Features/Documento/DocumentoResource.php` | `App\Features\Documento` |
| `config/filesystems.php` | — (edição) |
| `tests/Unit/...` | `Tests\Unit\...` |

---

## 13. Verificação de qualidade

```bash
composer lint          # Pint
composer refactor      # Rector
composer test:types    # Larastan nível 9
composer test          # pipeline completa (coverage 100% + type-coverage 100%)
```

---

## 14. SYSTEM_SPEC a actualizar (Fase 3a)

- `docs/system_spec/03-models/documento.md` — reescrever (substitui placeholder EN `Document`)
- `docs/system_spec/02-shared/enums.md` — `EstadoDocumento` (substitui `DocumentStatus`)
- `docs/system_spec/02-shared/estados.md` — nomenclatura PT; 7 estados; mapeamento estado→disco; states read-only
- `docs/system_spec/06-config.md` — 5 discos de storage PT
- `docs/system_spec/00-index.md` — novos ficheiros (enum `EstadoDocumento`, interface + 7 state objects, `DocumentoPolicy`, slice `Documento`)
