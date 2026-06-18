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

## Entidade (app/Models/Entidade.php)

Representa os intervenientes do domínio financeiro: Clientes, Fornecedores e a Empresa Mãe. Uma entidade pode ser simultaneamente cliente e fornecedor. Só pode existir uma `Entidade` com `e_empresa_aplicacao = true` — invariante garantida pela Action (issue futura) e reforçada em produção MySQL por índice parcial único.

**Tabela:** `entidades`

### Colunas

| Coluna | Tipo BD | Tipo PHP | Notas |
|---|---|---|---|
| `id` | `uuid` PK | `string` | UUIDv7 via `HasUuids` |
| `nome` | `string(255)` | `string` | Com índice |
| `nif` | `string(255)` | `string` | Com índice |
| `e_cliente` | `boolean` | `bool` | Default `false`; cast `'boolean'` |
| `e_fornecedor` | `boolean` | `bool` | Default `false`; cast `'boolean'` |
| `e_empresa_aplicacao` | `boolean` | `bool` | Default `false`; cast `'boolean'` |
| `created_at` | `timestamp` | `Carbon` | Auto via `timestamps()` |
| `updated_at` | `timestamp` | `Carbon` | Auto via `timestamps()` |

### Índice parcial MySQL

`unica_empresa_mae_idx` — `CREATE UNIQUE INDEX ... WHERE (e_empresa_aplicacao = 1)`. Criado condicionalmente (`DB::getDriverName() === 'mysql'`); SQLite (testes) não suporta índices parciais condicionais.

### Traits e atributos

- `HasUuids` — UUID como PK, não autoincrement (UUIDv7 por default)
- `HasFactory` — `EntidadeFactory`
- `#[Table('entidades')]`
- `#[Fillable(['nome', 'nif', 'e_cliente', 'e_fornecedor', 'e_empresa_aplicacao'])]`
- `#[UsePolicy(EntidadePolicy::class)]`
- Casts via `casts()`: `'e_cliente'`, `'e_fornecedor'`, `'e_empresa_aplicacao'` → `'boolean'`

### Scopes

| Scope | Filtro |
|---|---|
| `scopeWhereCliente(Builder<Entidade>)` | `e_cliente = true` |
| `scopeWhereFornecedor(Builder<Entidade>)` | `e_fornecedor = true` |
| `scopeWhereEmpresaAplicacao(Builder<Entidade>)` | `e_empresa_aplicacao = true` |

### Factory states

| State | `e_cliente` | `e_fornecedor` | `e_empresa_aplicacao` |
|---|---|---|---|
| `cliente()` | `true` | `false` | `false` |
| `fornecedor()` | `false` | `true` | `false` |
| `clienteEFornecedor()` | `true` | `true` | `false` |
| `empresaAplicacao()` | `true` | `true` | `true` |

> A empresa mãe é obrigatoriamente cliente e fornecedor — emite documentos (fornecedor) e recebe-os (cliente).

### Relações

_Pendentes — definidas em issues futuras (Document → Entidade)._

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
