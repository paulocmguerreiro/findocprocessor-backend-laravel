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
| `deleted_at` | `timestamp` nullable | `?Carbon` | SoftDeletes — `null` = activa; preenchido = inactiva |

---

## Traits e atributos

- `HasUuids` — UUID como PK, não autoincrement (UUIDv7 por default)
- `HasFactory` — `EntidadeFactory`
- `SoftDeletes` — `delete()` faz soft delete (preenche `deleted_at`); queries excluem registos inactivos por defeito
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

| State | `e_cliente` | `e_fornecedor` | `e_empresa_aplicacao` | `deleted_at` |
|---|---|---|---|---|
| `cliente()` | `true` | `false` | `false` | `null` |
| `fornecedor()` | `false` | `true` | `false` | `null` |
| `clienteEFornecedor()` | `true` | `true` | `false` | `null` |
| `empresaAplicacao()` | `true` | `true` | `true` | `null` |
| `inativa()` | — | — | — | `now()` |

> A empresa mãe é obrigatoriamente cliente e fornecedor — emite documentos (fornecedor) e recebe-os (cliente).
> `inativa()` usa `->state(['deleted_at' => now()])` — define o atributo directamente, sem chamar `delete()`, logo não dispara eventos do `RegistaActividade`.

---

## Relações

`Documento` referencia `Entidade` via `id_fornecedor` e `id_cliente` (lado `belongsTo` em `Documento`). Sem relação inversa `hasMany` definida neste Model.

---

## Resource `EntidadeResource`

**Ficheiro:** `app/Features/Entidade/EntidadeResource.php`

7 campos (`deleted_at` adicionado com o SoftDelete):

| Campo | Tipo JSON | Notas |
|---|---|---|
| `id` | `string` (uuid) | |
| `nome` | `string` | |
| `nif` | `string` | |
| `e_cliente` | `boolean` | |
| `e_fornecedor` | `boolean` | |
| `e_empresa_aplicacao` | `boolean` | |
| `deleted_at` | `string\|null` | ISO 8601; `null` se activa |

---

## Policy

`#[UsePolicy(EntidadePolicy::class)]` — auto-ligada pelo atributo. `hasPermissionTo('entidades.<accao>')` por ability. Matriz role→permission e detalhe em `04-infra/autorizacao.md`.
