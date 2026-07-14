# System Spec — Model: EtapaDocumento

> `app/Models/EtapaDocumento.php` · Tabela: `etapas_documento` · Issue #56

---

## Tabela `etapas_documento`

| Coluna | Tipo BD | Nullable | Índice | Notas |
|---|---|---|---|---|
| `id` | `uuid` PK | Não | PK | UUIDv7 via `HasUuids` |
| `id_documento` | `uuid` FK | Não | FK | → `documentos.id`; `cascadeOnDelete()` + `cascadeOnUpdate()` (histórico segue o documento) |
| `estado` | `string(50)` | Não | simples | Cast → `EstadoDocumento`; a etapa atingida |
| `passo` | `string(50)` | Sim | — | Cast → `EtapaExtracao`; `null` = linha de negócio, preenchido = linha de IA (#94) |
| `resultado` | `string(20)` | Sim | — | Cast → `ResultadoEtapa`; `null` = linha de negócio, preenchido = linha de IA (#94) |
| `motivo` | `text` | Sim | — | Motivo/resposta/nota; pode conter detalhe sensível |
| `id_utilizador` | `bigint unsigned` FK | Sim | FK | → `users.id`; `restrictOnDelete()` (Issue #68 — era `nullOnDelete`) + `cascadeOnUpdate()`; `null` = passo automático (sistema) |
| `created_at` | `timestamp` | Sim | — | Data+hora da etapa; **sem `updated_at`** (append-only) |

**Notas:**
- **Sem `updated_at`** — tabela append-only; cada transição de estado cria uma linha nova; nunca há updates.
- `id_utilizador` é `bigint` (não UUID) porque `users.id` é `bigint auto_increment` (sem `HasUuids`). Desvio documentado no Brief/Debrief da Issue #56.
- `cascadeOnDelete()` em `id_documento` — histórico não existe sem o documento.
- `restrictOnDelete()` em `id_utilizador` (Issue #68) — um utilizador que registou etapas não pode ser hard-deleted; `EliminarUtilizadorAction` cai no soft delete, preservando a autoria da etapa.
- `cascadeOnUpdate()` em ambas as FKs (migration `add_cascade_on_update_to_domain_fks`, 2026-07-14) — sem esta cascade, um `UPDATE` à PK do documento ou do utilizador falharia por violação de FK; prepara para uma futura reconciliação/agregação de bases de dados que precise de remapear UUIDs.
- **`passo`/`resultado` (#94)** — colunas nullable acrescentadas por migration própria
  (`add_passo_resultado_to_etapas_documento_table`), sem migração de dados: linhas existentes ficam
  com ambas a `null` (linha de negócio, comportamento inalterado). Uma linha de IA (gravada por
  `RegistarEtapaExtracaoAction`, ver `01-features/documento.md`) tem `estado` igual ao `status` actual
  do `Documento` (não muda) e `passo`/`resultado` preenchidos. Ver `02-shared/estados.md` — "modelo
  de 2 dimensões" para a distinção completa.

---

## Model `EtapaDocumento`

**Ficheiro:** `app/Models/EtapaDocumento.php`

```php
#[Table('etapas_documento')]
#[Fillable(['id_documento', 'estado', 'passo', 'resultado', 'motivo', 'id_utilizador'])]
class EtapaDocumento extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;  // append-only

    protected function casts(): array
    {
        return [
            'estado' => EstadoDocumento::class,
            'passo' => EtapaExtracao::class,
            'resultado' => ResultadoEtapa::class,
        ];
    }
}
```

### PHPDoc `@property-read`

```php
/**
 * @property-read string $id
 * @property-read string $id_documento
 * @property-read EstadoDocumento $estado
 * @property-read ?EtapaExtracao $passo
 * @property-read ?ResultadoEtapa $resultado
 * @property-read ?string $motivo
 * @property-read ?int $id_utilizador    // bigint → users.id
 * @property-read Carbon $created_at
 * @property-read Documento $documento
 * @property-read ?User $utilizador
 */
```

`passo`/`resultado` nullable — Eloquent resolve `null` sem exigir um caso "vazio" no enum (cast
built-in do Laravel já trata `null` antes de instanciar o backed enum).

### Append-only — `const UPDATED_AT = null`

`public const UPDATED_AT = null` — Eloquent passa a gerir apenas `created_at`; `usesTimestamps()` continua `true`. Larastan 9 aceita esta forma sem ajustes.

### Casts

```php
protected function casts(): array
{
    return [
        'estado' => EstadoDocumento::class,
        'passo' => EtapaExtracao::class,
        'resultado' => ResultadoEtapa::class,
    ];
}
```

### Relações

```php
public function documento(): BelongsTo  // → Documento (id_documento)
public function utilizador(): BelongsTo // → User (id_utilizador); nullable
```

### Auditoria

**Não usa `RegistaActividade`** — esta tabela *é* o histórico de domínio; aplicar audit trail técnico seria registo duplicado. Ver `04-infra/audit-trail.md` para a distinção auditoria de domínio vs. técnica.

---

## Factory `EtapaDocumentoFactory`

**Ficheiro:** `database/factories/EtapaDocumentoFactory.php`

Base (`definition()`) = `estado Pendente`, `id_utilizador = null`, `motivo = null`.

| State | `estado` | `motivo` | `id_utilizador` |
|---|---|---|---|
| base | `Pendente` | `null` | `null` |
| `processado()` | `Processado` | `null` | `null` |
| `erro()` | `Erro` | `faker->sentence()` | `null` |
| `perigoso()` | `Perigoso` | `faker->sentence()` | `null` |
| `manual()` | `Processado` | `null` | `User::factory()` |

**`passoIa(EtapaExtracao $passo = NecessitaOcr, ResultadoEtapa $resultado = Sucesso)` (#94)** — não
altera `estado`; só define `passo`/`resultado`, simulando uma linha de IA gravada por
`RegistarEtapaExtracaoAction` sobre o estado de negócio actual.

---

## Relação inversa — `Documento->historico`

Ver `03-models/documento.md` para a relação `hasMany` adicionada ao `Documento` (Issue #56).

---

## Invariantes de domínio

- **Append-only:** nunca fazer `update()` numa `EtapaDocumento`. As Actions de transição criam uma linha nova por transição, **dentro da mesma `DB::transaction()`** da mudança de `Documento.status`.
- `estado` nunca é `null` — a etapa atingida é sempre conhecida.
- `motivo` só é relevante nos estados `Erro` e `Perigoso` (convencional, não enforçado no Model).

---

## Notas arquitecturais

- **Model não-`final`** — coerente com os restantes Models (`Documento`, `Entidade`); o ArchTest "actions are final" não cobre Models.
- **`id_utilizador` como `?int`** — desvio à convenção UUID do domínio; `users.id` é bigint. Migrar `users` para UUID está fora de âmbito (impacto em Sanctum/Spatie/auth).
- **Resource e endpoint de leitura** ficam na issue de Lógica (#57 ou subsequente) — fora de âmbito desta camada de modelo.
