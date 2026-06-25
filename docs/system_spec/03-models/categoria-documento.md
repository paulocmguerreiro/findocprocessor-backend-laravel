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

---

## Traits e atributos

- `HasUuids` — UUID como PK, não autoincrement
- `HasFactory` — `CategoriaDocumentoFactory`
- `RegistaActividade` — audit trail (logFillable, logOnlyDirty); sem campos excluídos. Ver `04-infra/audit-trail.md`
- `#[Table('categorias_documento')]` — nome explícito (não inferível pelo Eloquent)
- `#[Fillable(['nome', 'slug', 'tipo_movimento'])]`
- Cast via método `casts()`: `'tipo_movimento' => TipoMovimento::class`

---

## Factory states

| State | Efeito |
|---|---|
| `comMovimentoDebito()` | `tipo_movimento = TipoMovimento::Debito` |
| `comMovimentoCredito()` | `tipo_movimento = TipoMovimento::Credito` |
| `comMovimentoNeutro()` | `tipo_movimento = TipoMovimento::Neutro` |

---

## Relações

_Pendentes — definidas em issues futuras (Document → CategoriaDocumento)._
