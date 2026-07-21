# System Spec — Model: Documento

> `app/Models/Documento.php` · Tabela: `documentos`

---

## Tabela `documentos`

| Coluna | Tipo BD | Nullable | Notas |
|---|---|---|---|
| `id` | `uuid` PK | Não | UUIDv7 via `HasUuids` |
| `estado` | `string(50)` | Não | Default `'PENDENTE'`; índice simples; cast → `EstadoDocumento` |
| `id_responsavel` | `bigint` FK | Sim | → `users.id`; `restrictOnDelete()` (era `nullOnDelete`) + `cascadeOnUpdate()`; autor do registo/upload (sempre o utilizador autenticado) |
| `id_fornecedor` | `uuid` FK | Sim | → `entidades.id`; `restrictOnDelete()` (era `nullOnDelete`) + `cascadeOnUpdate()` |
| `id_cliente` | `uuid` FK | Sim | → `entidades.id`; `restrictOnDelete()` (era `nullOnDelete`) + `cascadeOnUpdate()` |
| `id_categoria` | `uuid` FK | Sim | → `categorias_documento.id`; `restrictOnDelete()` (era `nullOnDelete`) + `cascadeOnUpdate()` |
| `valor` | `decimal(15,2)` | Sim | Cast `decimal:2` → devolve `string` em PHP (não `float`) |
| `data_documento` | `date` | Sim | Índice simples; cast → `Carbon` |
| `nome_ficheiro_original` | `string(500)` | Não | Nome original do ficheiro no upload |
| `disco_storage` | `string(50)` | Não | Nome do disco Laravel onde reside o ficheiro |
| `nome_ficheiro_storage` | `string(500)` | Não | Nome do ficheiro no disco |
| `hash_sha256` | `string(64)` | Não | SHA-256 do conteúdo; índice único (previne duplicados) |
| `created_at` / `updated_at` | `timestamp` | — | `timestamps()` |

**Nota:** FKs de domínio nullable por design — campos de domínio podem estar a `null` em `Pendente` (registo automático iniciado sem dados completos).

**`id_responsavel`** — FK `bigint` para `users.id` (o PK de `users` é incremental, não UUID). É o autor da entrada: definido pela `RegistarDocumentoManualAction` e pela `ReceberUploadDocumentoAction` a partir de `Auth::id()` — **nunca vem do cliente** (campo derivado server-side, como o `hash_sha256`); está sempre preenchido à criação. A FK é `restrictOnDelete()`: um utilizador responsável por documentos não pode ser hard-deleted — `EliminarUtilizadorAction` cai no soft delete, preservando a autoria. As transições de pipeline (`Marcar*`) **não** alteram o responsável.

**`cascadeOnUpdate()` em todas as FKs de domínio** — adicionado numa migration posterior (`add_cascade_on_update_to_domain_fks`, 2026-07-14). Mantém o `onDelete` já definido em cada FK; sem esta cascade, um `UPDATE` à PK de um registo pai (`entidades`, `categorias_documento`, `users`) falharia por violação de FK. Prepara para uma futura reconciliação/agregação de bases de dados que precise de remapear UUIDs.

---

## Model `Documento`

**Ficheiro:** `app/Models/Documento.php`

```php
#[Table('documentos')]
#[Fillable(['estado', 'id_responsavel', 'id_fornecedor', 'id_cliente', 'id_categoria', 'valor',
    'data_documento', 'nome_ficheiro_original', 'disco_storage',
    'nome_ficheiro_storage', 'hash_sha256'])]
#[UsePolicy(DocumentoPolicy::class)]
class Documento extends Model
{
    use HasFactory, HasUuids, RegistaActividade;
}
```

### PHPDoc `@property-read`

```php
/**
 * @property-read string $id
 * @property-read EstadoDocumento $estado
 * @property-read ?int $id_responsavel
 * @property-read ?string $id_fornecedor
 * @property-read ?string $id_cliente
 * @property-read ?string $id_categoria
 * @property-read ?string $valor          // cast decimal:2 → string
 * @property-read ?Carbon $data_documento
 * @property-read string $nome_ficheiro_original
 * @property-read string $disco_storage
 * @property-read string $nome_ficheiro_storage
 * @property-read string $hash_sha256
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read ?User $responsavel
 * @property-read ?Entidade $fornecedor
 * @property-read ?Entidade $cliente
 * @property-read ?CategoriaDocumento $categoria
 * @property-read ?ExtracaoDocumento $extracao
 */
```

### Casts

```php
protected function casts(): array
{
    return [
        'estado'         => EstadoDocumento::class,
        'valor'          => 'decimal:2',    // devolve string, não float
        'data_documento' => 'date',
    ];
}
```

### Audit trail (`RegistaActividade`)

Campos excluídos do log (RGPD / PII indirecta):
```php
protected function atributosExcluidosDaActividade(): array
{
    return ['hash_sha256', 'disco_storage', 'nome_ficheiro_storage'];
}
```

### Método `estado()`

```php
public function estado(): EstadoDocumentoInterface
{
    return match ($this->estado) {
        EstadoDocumento::Pendente       => DocumentoPendente::deDocumento($this),
        EstadoDocumento::AnaliseMalware => DocumentoAnaliseMalware::deDocumento($this),
        EstadoDocumento::AnaliseTexto   => DocumentoAnaliseTexto::deDocumento($this),
        EstadoDocumento::AnaliseOcr     => DocumentoAnaliseOcr::deDocumento($this),
        EstadoDocumento::AnaliseIaLocal => DocumentoAnaliseIaLocal::deDocumento($this),
        EstadoDocumento::AnaliseCloud   => DocumentoAnaliseCloud::deDocumento($this),
        EstadoDocumento::Processado     => DocumentoProcessado::deDocumento($this),
        EstadoDocumento::Erro           => DocumentoErro::deDocumento($this),
        EstadoDocumento::Perigoso       => DocumentoPerigoso::deDocumento($this),
    };
}
```

`match` **sem `default`** — Larastan 9 valida a exaustividade dos 9 casos. Adicionar um 10.º estado ao enum sem tratar aqui produz erro em `composer test:types`.

### PHPDoc `@property-read` — adição

```php
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EtapaDocumento> $historico
```

### Relações

```php
public function responsavel(): BelongsTo // → User (id_responsavel)
public function fornecedor(): BelongsTo  // → Entidade (id_fornecedor) — withTrashed()
public function cliente(): BelongsTo     // → Entidade (id_cliente) — withTrashed()
public function categoria(): BelongsTo   // → CategoriaDocumento (id_categoria) — withTrashed()
public function historico(): HasMany     // → EtapaDocumento (id_documento), orderBy created_at asc
public function extracao(): HasOne       // → ExtracaoDocumento (id_documento)
```

> **`withTrashed()` em `fornecedor()`, `cliente()` e `categoria()`** — documentos históricos continuam a carregar a entidade/categoria mesmo após soft delete. Sem `withTrashed()`, a relação devolveria `null` quando o registo está inactivo, apagando o histórico do interveniente/classificação.

**`historico`** — relação `hasMany` com FK explícita `id_documento`; ordenada por `created_at` ascendente (linha temporal). Ver `03-models/etapa-documento.md` para detalhe do Model.

**`extracao`** — relação `hasOne` para o scratch space de extracção, **sem `withDefault()`**: `null` é
um valor legítimo (documento nunca entrou no pipeline de extracção, ex.: registo manual via
`RegistarDocumentoManualAction`; ou linha já eliminada ao atingir estado terminal —
`RegraEliminarExtracaoTerminal`). Ver `03-models/extracao-documento.md`.

### Scopes

| Scope | Assinatura | Filtra |
|---|---|---|
| `whereEstado` | `(Builder $query, EstadoDocumento $estado)` | Genérico — qualquer estado |
| `whereProcessado` | `(Builder $query)` | `PROCESSADO` |
| `wherePendente` | `(Builder $query)` | `PENDENTE` |
| `wherePerigoso` | `(Builder $query)` | `PERIGOSO` |
| `whereErro` | `(Builder $query)` | `ERRO` |

---

## Factory `DocumentoFactory`

**Ficheiro:** `database/factories/DocumentoFactory.php`

Base (`definition()`) = estado `Processado` com todos os campos preenchidos. 9 states disponíveis:

| State | `estado` | `disco_storage` | FKs/valor/data |
|---|---|---|---|
| `pendente()` | `Pendente` | `entrada` | null |
| `analiseMalware()` | `AnaliseMalware` | `entrada` | null |
| `analiseTexto()` | `AnaliseTexto` | `entrada` | null |
| `analiseOcr()` | `AnaliseOcr` | `entrada` | null |
| `analiseIaLocal()` | `AnaliseIaLocal` | `enviado` | null |
| `analiseCloud()` | `AnaliseCloud` | `enviado` | null |
| `processado()` | `Processado` | `processado` | preenchidos |
| `erro()` | `Erro` | `erro` | null |
| `perigoso()` | `Perigoso` | `perigoso` | null |

`hash_sha256` gerado com `hash('sha256', fake()->unique()->sha256())` — garante 64 chars hex únicos por teste.

---

> Policy `DocumentoPolicy`, DTOs e Resource `DocumentoResource` estão documentados em
> `03-models/documento-policy-resource.md` (extraído por limiar de tamanho, WRN-033).

---

## Notas arquitecturais

- **Cast `decimal:2` devolve `string`** — `@property-read ?string $valor`. A conversão para `float` é responsabilidade do Resource (não do Model nem do DTO de input).
- **Model não é `final`** — coerente com `Entidade`/`CategoriaDocumento`; o ArchTest "actions are final" não cobre Models.
- **Sem Repository** — desvio aceite; listagem directa no Eloquent (sem queries complexas nesta camada). Uma futura revisão da camada de Lógica reavaliará se a `ListarDocumentosAction` justifica Repository.
- **`#[UsePolicy(DocumentoPolicy::class)]`** registado no Model — auto-descoberta da Policy granular (`hasPermissionTo`).
- **Hard-delete deliberado** — `Documento` não usa `SoftDeletes`; decisão documentada em `../02-shared/soft-delete.md`.
