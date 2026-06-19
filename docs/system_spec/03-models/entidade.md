# System Spec — Model: Entidade

> `app/Models/Entidade.php`

Representa os intervenientes do domínio financeiro: Clientes, Fornecedores e a Empresa Mãe. Uma entidade pode ser simultaneamente cliente e fornecedor. Só pode existir uma `Entidade` com `e_empresa_aplicacao = true` — invariante garantida pela Action e reforçada em produção MySQL por índice parcial único.

**Tabela:** `entidades`

---

## Colunas

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

---

## Índice parcial MySQL

`unica_empresa_mae_idx` — `CREATE UNIQUE INDEX ... WHERE (e_empresa_aplicacao = 1)`. Criado condicionalmente (`DB::getDriverName() === 'mysql'`); SQLite (testes) não suporta índices parciais condicionais.

---

## Traits e atributos

- `HasUuids` — UUID como PK, não autoincrement (UUIDv7 por default)
- `HasFactory` — `EntidadeFactory`
- `#[Table('entidades')]`
- `#[Fillable(['nome', 'nif', 'e_cliente', 'e_fornecedor', 'e_empresa_aplicacao'])]`
- `#[UsePolicy(EntidadePolicy::class)]`
- Casts via `casts()`: `'e_cliente'`, `'e_fornecedor'`, `'e_empresa_aplicacao'` → `'boolean'`

---

## Scopes

| Scope | Filtro |
|---|---|
| `scopeWhereCliente(Builder<Entidade>)` | `e_cliente = true` |
| `scopeWhereFornecedor(Builder<Entidade>)` | `e_fornecedor = true` |
| `scopeWhereEmpresaAplicacao(Builder<Entidade>)` | `e_empresa_aplicacao = true` |

---

## Factory states

| State | `e_cliente` | `e_fornecedor` | `e_empresa_aplicacao` |
|---|---|---|---|
| `cliente()` | `true` | `false` | `false` |
| `fornecedor()` | `false` | `true` | `false` |
| `clienteEFornecedor()` | `true` | `true` | `false` |
| `empresaAplicacao()` | `true` | `true` | `true` |

> A empresa mãe é obrigatoriamente cliente e fornecedor — emite documentos (fornecedor) e recebe-os (cliente).

---

## Relações

_Pendentes — definidas em issues futuras (Document → Entidade)._
