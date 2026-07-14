# System Spec — Model: CategoriaDocumento

> `app/Models/CategoriaDocumento.php`

Entidade de referência. Classifica documentos financeiros e define o tipo de movimento contabilístico.

**Tabela:** `categorias_documento`

---

## Colunas

| Coluna | Tipo BD | Tipo PHP | Notas |
|---|---|---|---|
| `id` | `uuid` PK | `string` | UUIDv7 via `HasUuids` |
| `nome` | `string(255)` | `string` | Com índice |
| `slug` | `string(255)` | `string` | Índice único |
| `tipo_movimento` | `string(50)` | `TipoMovimento` | Cast para enum |
| `created_at` | `timestamp` | `Carbon` | Auto via `timestamps()` |
| `updated_at` | `timestamp` | `Carbon` | Auto via `timestamps()` |
| `deleted_at` | `timestamp` nullable | `?Carbon` | SoftDeletes |

---

## Traits e atributos

- `HasUuids` — UUID como PK, não autoincrement
- `HasFactory` — `CategoriaDocumentoFactory`
- `RegistaActividade` — audit trail (logFillable, logOnlyDirty); sem campos excluídos. Ver `04-infra/audit-trail.md`
- `SoftDeletes` — `delete()` faz soft delete; `all()` exclui inactivas por defeito
- `#[Table('categorias_documento')]` — nome explícito (não inferível pelo Eloquent)
- `#[Fillable(['nome', 'slug', 'tipo_movimento'])]`
- `#[UsePolicy(CategoriaDocumentoPolicy::class)]`
- Cast via método `casts()`: `'tipo_movimento' => TipoMovimento::class`

---

## Factory states

| State | Efeito |
|---|---|
| `comMovimentoDebito()` | `tipo_movimento = TipoMovimento::Debito` |
| `comMovimentoCredito()` | `tipo_movimento = TipoMovimento::Credito` |
| `comMovimentoNeutro()` | `tipo_movimento = TipoMovimento::Neutro` |
| `inativa()` | `deleted_at = now()` — categoria soft-deleted |

---

## Relações

`Documento` referencia `CategoriaDocumento` via `id_categoria` (lado `belongsTo` em `Documento`). A relação usa `->withTrashed()` para carregar categorias inactivas. Sem relação inversa `hasMany` definida neste Model.

---

## Resource `CategoriaDocumentoResource`

**Ficheiro:** `app/Features/CategoriaDocumento/CategoriaDocumentoResource.php`

| Campo | Tipo | Fonte |
|---|---|---|
| `id` | `string` | directo |
| `nome` | `string` | directo |
| `slug` | `string` | directo |
| `tipo_movimento` | `string` | `->value` |
| `deleted_at` | `?string` | `?->toIso8601String()` — null quando activa |

---

## Policy

`#[UsePolicy(CategoriaDocumentoPolicy::class)]` — auto-ligada pelo atributo. `hasPermissionTo('categorias-documento.<accao>')` por ability. Matriz role→permission e detalhe em `04-infra/autorizacao.md`.
