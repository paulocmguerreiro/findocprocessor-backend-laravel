# Brief — Issue #27: Entidade — model layer

**Data:** 2026-06-18
**Slug:** `entidade-model-layer`
**Branch:** `feat/entidade-model-layer`
**Issue:** [#27](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/27)

---

## Objectivo

Criar a camada de modelo para a entidade `Entidade` do domínio financeiro. Cobre migration, Model Eloquent, Factory com states, Policy CRUD e testes.

---

## Contexto de domínio

`Entidade` representa os intervenientes do domínio: Clientes, Fornecedores e a Empresa Mãe (a empresa que usa a aplicação). Uma entidade pode ser simultaneamente cliente e fornecedor. Só pode existir **uma** `Entidade` com `e_empresa_aplicacao = true` — invariante garantida pela aplicação (Action) e reforçada em produção MySQL por índice parcial único.

---

## O que fazer

| Componente | Ficheiro |
|---|---|
| Migration | `database/migrations/YYYY_MM_DD_HHMMSS_create_entidades_table.php` |
| Model | `app/Models/Entidade.php` |
| Factory | `database/factories/EntidadeFactory.php` |
| Policy | `app/Policies/EntidadePolicy.php` |
| Testes Model | `tests/Feature/Models/EntidadeTest.php` |
| Testes Policy | `tests/Feature/Policies/EntidadePolicyTest.php` |

---

## Decisões de design

### Repository
**Dispensado** — esta issue é camada de modelo puro (migration + model + factory + policy). Sem queries complexas; sem lógica partilhada entre Actions. Repository será criado na issue `/cria-issue-persistencia`.

### Índice parcial MySQL (invariante Empresa Mãe)
Duas camadas de protecção:
1. **Aplicação** — validação PHP na Action/FormRequest (issue futura de lógica)
2. **Base de dados** — índice parcial único MySQL via `DB::statement()` na migration, protegido por `DB::getDriverName() === 'mysql'` para não falhar em SQLite (testes)

### Boolean fields
`e_cliente`, `e_fornecedor`, `e_empresa_aplicacao` são booleanos puros — sem enum. Cast `'boolean'` via método `casts()` garante que MySQL TINYINT é convertido para `bool` PHP.

### Policy
Segue o padrão da codebase (`CategoriaDocumentoPolicy`): `?User $utilizador` nullable, retorna `true` para todos os métodos. Regras de ownership/roles serão definidas nas Actions (issue de lógica futura).

---

## Padrões obrigatórios (referência codebase)

- `#[Table('entidades')]`, `#[Fillable([...])]` como atributos PHP no Model
- `HasUuids` (UUIDv7 por default — lexicographically sortable)
- `@property-read` completo para todas as colunas (PHPStan nível 9)
- `strict_types=1` em todos os ficheiros
- Colunas com `->comment(...)` nas migrations
- Factory states com `$this->state([...])`

---

## Riscos identificados

| Risco | Mitigação |
|---|---|
| SQLite não suporta `WHERE` em `CREATE UNIQUE INDEX` | Proteger com `if (DB::getDriverName() === 'mysql')` |
| `down()` do índice parcial — sintaxe MySQL-specific | `DROP INDEX IF EXISTS unica_empresa_mae_idx ON entidades` dentro do mesmo guard MySQL |
| `@property-read` booleano — MySQL armazena TINYINT | Cast `'boolean'` no `casts()` resolve; tipo PHP declarado como `bool` |
| Factory `empresaAplicacao` — só pode haver 1 em BD | Testes de factory não criam dois registos com este state na mesma BD; usar `RefreshDatabase` |

---

## Questões em aberto

Nenhuma — issue completamente especificada.

---

## Fora de âmbito

- Repository e DTOs (issue separada: `/cria-issue-persistencia`)
- Actions, Controller, FormRequests (issue separada: `/cria-issue-logica`)
- Endpoints de API
