# System Spec — Model: Documento

> `app/Models/Documento.php` · Tabela: `documentos` · Issue #45

---

## Tabela `documentos`

| Coluna | Tipo BD | Nullable | Notas |
|---|---|---|---|
| `id` | `uuid` PK | Não | UUIDv7 via `HasUuids` |
| `status` | `string(50)` | Não | Default `'PENDENTE'`; índice simples; cast → `EstadoDocumento` |
| `id_responsavel` | `bigint` FK | Sim | → `users.id`; `restrictOnDelete()` (Issue #68 — era `nullOnDelete`) + `cascadeOnUpdate()`; autor do registo/upload (sempre o utilizador autenticado) |
| `id_fornecedor` | `uuid` FK | Sim | → `entidades.id`; `restrictOnDelete()` (Issue #69 — era `nullOnDelete`) + `cascadeOnUpdate()` |
| `id_cliente` | `uuid` FK | Sim | → `entidades.id`; `restrictOnDelete()` (Issue #69 — era `nullOnDelete`) + `cascadeOnUpdate()` |
| `id_categoria` | `uuid` FK | Sim | → `categorias_documento.id`; `restrictOnDelete()` (Issue #70 — era `nullOnDelete`) + `cascadeOnUpdate()` |
| `valor` | `decimal(15,2)` | Sim | Cast `decimal:2` → devolve `string` em PHP (não `float`) |
| `data_documento` | `date` | Sim | Índice simples; cast → `Carbon` |
| `nome_ficheiro_original` | `string(500)` | Não | Nome original do ficheiro no upload |
| `disco_storage` | `string(50)` | Não | Nome do disco Laravel onde reside o ficheiro |
| `nome_ficheiro_storage` | `string(500)` | Não | Nome do ficheiro no disco |
| `hash_sha256` | `string(64)` | Não | SHA-256 do conteúdo; índice único (previne duplicados) |
| `created_at` / `updated_at` | `timestamp` | — | `timestamps()` |

**Nota:** FKs de domínio nullable por design — campos de domínio podem estar a `null` em `Pendente` (registo automático iniciado sem dados completos).

**`id_responsavel`** (Issue #57 — revisão) — FK `bigint` para `users.id` (o PK de `users` é incremental, não UUID). É o autor da entrada: definido pela `RegistarDocumentoManualAction` e pela `ReceberUploadDocumentoAction` a partir de `Auth::id()` — **nunca vem do cliente** (campo derivado server-side, como o `hash_sha256`); está sempre preenchido à criação. A FK é `restrictOnDelete()` (Issue #68): um utilizador responsável por documentos não pode ser hard-deleted — `EliminarUtilizadorAction` cai no soft delete, preservando a autoria. As transições de pipeline (`Marcar*`) **não** alteram o responsável.

**`cascadeOnUpdate()` em todas as FKs de domínio** — adicionado numa migration posterior (`add_cascade_on_update_to_domain_fks`, 2026-07-14). Mantém o `onDelete` já definido em cada FK; sem esta cascade, um `UPDATE` à PK de um registo pai (`entidades`, `categorias_documento`, `users`) falharia por violação de FK. Prepara para uma futura reconciliação/agregação de bases de dados que precise de remapear UUIDs.

---

## Model `Documento`

**Ficheiro:** `app/Models/Documento.php`

```php
#[Table('documentos')]
#[Fillable(['status', 'id_responsavel', 'id_fornecedor', 'id_cliente', 'id_categoria', 'valor',
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
 * @property-read EstadoDocumento $status
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
 */
```

### Casts

```php
protected function casts(): array
{
    return [
        'status'         => EstadoDocumento::class,
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
public function estado(): ContratoEstadoDocumento
{
    return match ($this->status) {
        EstadoDocumento::Pendente        => DocumentoPendente::deDocumento($this),
        EstadoDocumento::AguardaEnvio    => DocumentoAguardaEnvio::deDocumento($this),
        EstadoDocumento::Enviado         => DocumentoEnviado::deDocumento($this),
        EstadoDocumento::AguardaResposta => DocumentoAguardaResposta::deDocumento($this),
        EstadoDocumento::Processado      => DocumentoProcessado::deDocumento($this),
        EstadoDocumento::Erro            => DocumentoErro::deDocumento($this),
        EstadoDocumento::Perigoso        => DocumentoPerigoso::deDocumento($this),
    };
}
```

`match` **sem `default`** — Larastan 9 valida a exaustividade dos 7 casos. Adicionar um 8.º estado ao enum sem tratar aqui produz erro em `composer test:types`.

### PHPDoc `@property-read` — adição Issue #56

```php
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EtapaDocumento> $historico
```

### Relações

```php
public function responsavel(): BelongsTo // → User (id_responsavel)
public function fornecedor(): BelongsTo  // → Entidade (id_fornecedor) — withTrashed() (Issue #69)
public function cliente(): BelongsTo     // → Entidade (id_cliente) — withTrashed() (Issue #69)
public function categoria(): BelongsTo   // → CategoriaDocumento (id_categoria) — withTrashed() (Issue #70)
public function historico(): HasMany     // → EtapaDocumento (id_documento), orderBy created_at asc
```

> **`withTrashed()` em `fornecedor()`, `cliente()` e `categoria()`** — documentos históricos continuam a carregar a entidade/categoria mesmo após soft delete. Sem `withTrashed()`, a relação devolveria `null` quando o registo está inactivo, apagando o histórico do interveniente/classificação.

**`historico`** — adicionado na Issue #56. Relação `hasMany` com FK explícita `id_documento`; ordenada por `created_at` ascendente (linha temporal). Ver `03-models/etapa-documento.md` para detalhe do Model.

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

Base (`definition()`) = estado `Processado` com todos os campos preenchidos. 7 states disponíveis:

| State | `status` | `disco_storage` | FKs/valor/data |
|---|---|---|---|
| `pendente()` | `Pendente` | `entrada` | null |
| `aguardaEnvio()` | `AguardaEnvio` | `entrada` | null |
| `enviado()` | `Enviado` | `enviado` | null |
| `aguardaResposta()` | `AguardaResposta` | `enviado` | null |
| `processado()` | `Processado` | `processado` | preenchidos |
| `erro()` | `Erro` | `erro` | null |
| `perigoso()` | `Perigoso` | `perigoso` | null |

`hash_sha256` gerado com `hash('sha256', fake()->unique()->sha256())` — garante 64 chars hex únicos por teste.

---

## Policy `DocumentoPolicy`

**Ficheiro:** `app/Policies/DocumentoPolicy.php`

Autorização granular via `hasPermissionTo(...)` — `viewAny`/`view` exigem `documentos.ver`; `create` exige `documentos.criar`; `update` exige `documentos.actualizar`; `delete` exige `documentos.eliminar`. As permissões e a matriz role→permission estão em `04-infra/autorizacao.md`.

```php
final class DocumentoPolicy
{
    public function viewAny(User $utilizador): bool  { return $utilizador->hasPermissionTo('documentos.ver'); }
    public function view(User $utilizador, Documento $documento): bool  { return $utilizador->hasPermissionTo('documentos.ver'); }
    public function create(User $utilizador): bool  { return $utilizador->hasPermissionTo('documentos.criar'); }
    public function update(User $utilizador, Documento $documento): bool  { return $utilizador->hasPermissionTo('documentos.actualizar'); }
    public function delete(User $utilizador, Documento $documento): bool  { return $utilizador->hasPermissionTo('documentos.eliminar'); }
}
```

---

## DTOs

Os DTOs do Documento pertencem à camada de lógica (#57) e estão documentados — em forma tabular — em `01-features/documento.md` (secção DTOs). Os DTOs originais da #45 (`CriarDocumentoManualDto`, `ActualizarDocumentoDto`) foram **substituídos** na #57 por `RegistarDocumentoManualDto` e `CorrigirDocumentoDto` (os da #45 incluíam campos de storage que não devem vir do cliente).

---

## Resource `DocumentoResource`

**Ficheiro:** `app/Features/Documento/DocumentoResource.php`

```json
{
  "id": "uuid",
  "status": "PROCESSADO",
  "id_responsavel": 1,
  "fornecedor": { ... },
  "cliente": { ... },
  "categoria": { ... },
  "valor": 1234.56,
  "data_documento": "2026-06-25",
  "nome_ficheiro_original": "fatura.pdf",
  "hash_sha256": "abc123...64chars",
  "criado_em": "2026-06-25T10:00:00.000000Z",
  "actualizado_em": "2026-06-25T10:00:00.000000Z"
}
```

- `status` → `->value` (string UPPER_SNAKE)
- `id_responsavel` → `int|null` (id do utilizador autor; não expõe nome/email — só o id)
- `valor` → conversão explícita `(float)` (cast `decimal:2` devolve `string`)
- Relações via `whenLoaded()` + `EntidadeResource` / `CategoriaDocumentoResource`
- **Não expõe** `disco_storage` nem `nome_ficheiro_storage` (detalhes internos / PII indirecta)

---

## Notas arquitecturais

- **Cast `decimal:2` devolve `string`** — `@property-read ?string $valor`. A conversão para `float` é responsabilidade do Resource (não do Model nem do DTO de input).
- **Model não é `final`** — coerente com `Entidade`/`CategoriaDocumento`; o ArchTest "actions are final" não cobre Models.
- **Sem Repository** — desvio aceite; listagem directa no Eloquent (sem queries complexas nesta camada). A issue de Lógica (#57) reavaliará se a `ListarDocumentosAction` justifica Repository.
- **`#[UsePolicy(DocumentoPolicy::class)]`** registado no Model — auto-descoberta da Policy granular (`hasPermissionTo`).
- **Hard-delete deliberado** — `Documento` não usa `SoftDeletes`; decisão documentada em `../02-shared/soft-delete.md`.
