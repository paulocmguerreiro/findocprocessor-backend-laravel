# System Spec — 03: Modelos Eloquent

> Actualizado automaticamente após cada Issue pela Fase 3 (documenta-issue).

## CategoriaDocumento (app/Models/CategoriaDocumento.php)

Entidade de referência. Classifica documentos financeiros e define o tipo de movimento contabilístico.

**Tabela:** `categorias_documento`

### Colunas

| Coluna | Tipo BD | Tipo PHP | Notas |
|---|---|---|---|
| `id` | `uuid` PK | `string` | UUIDv7 via `HasUuids` |
| `nome` | `string(255)` | `string` | Com índice |
| `slug` | `string(255)` | `string` | Índice único |
| `tipo_movimento` | `string(50)` | `TipoMovimento` | Cast para enum |
| `created_at` | `timestamp` | `Carbon` | Auto via `timestamps()` |
| `updated_at` | `timestamp` | `Carbon` | Auto via `timestamps()` |

### Traits e atributos

- `HasUuids` — UUID como PK, não autoincrement
- `HasFactory` — `CategoriaDocumentoFactory`
- `#[Table('categorias_documento')]` — nome explícito (não inferível pelo Eloquent)
- `#[Fillable(['nome', 'slug', 'tipo_movimento'])]`
- Cast via método `casts()`: `'tipo_movimento' => TipoMovimento::class`

### Factory states

| State | Efeito |
|---|---|
| `comMovimentoDebito()` | `tipo_movimento = TipoMovimento::Debito` |
| `comMovimentoCredito()` | `tipo_movimento = TipoMovimento::Credito` |
| `comMovimentoNeutro()` | `tipo_movimento = TipoMovimento::Neutro` |

### Relações

_Pendentes — definidas em issues futuras (Document → CategoriaDocumento)._

---

## Document (app/Models/Document.php)

_Pendente._

Campos planeados:
- `id` (uuid)
- `status` (string — DocumentStatus enum)
- `original_filename`
- `stored_path`
- `sha256_hash`
- `tipo_documento`
- `categoria`
- `fornecedor`
- `cliente`
- `valor_total`
- `data_documento`
- `nif_fornecedor` (sensível — não logar)
- `nif_cliente` (sensível — não logar)
- `error_message`
- `created_at`
- `updated_at`
