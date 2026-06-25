# System Spec — Model: Entidade

> `app/Models/Entidade.php`

Representa os intervenientes do domínio financeiro: Clientes, Fornecedores e a Empresa Mãe. Uma entidade pode ser simultaneamente cliente e fornecedor. Só pode existir uma `Entidade` com `e_empresa_aplicacao = true` — invariante garantida exclusivamente pela Action (`RegraUnicidadeEmpresaMae`).

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

## Traits e atributos

- `HasUuids` — UUID como PK, não autoincrement (UUIDv7 por default)
- `HasFactory` — `EntidadeFactory`
- `RegistaActividade` — audit trail; sobrepõe `atributosExcluidosDaActividade()` → `['nif']` (dado fiscal — RGPD). Ver `04-infra/audit-trail.md`
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
