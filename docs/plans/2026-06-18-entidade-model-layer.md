# Plano — Issue #27: Entidade — model layer

**Data:** 2026-06-18
**Branch:** `feat/entidade-model-layer`
**Comando de testes:** `composer test`

---

## Tarefas

### T1 — Migration `create_entidades_table`

**Ficheiro:** `database/migrations/YYYY_MM_DD_HHMMSS_create_entidades_table.php`

Criar via `php artisan make:migration create_entidades_table --no-interaction`.

`up()`:
- `$table->uuid('id')->primary()->comment(...)`
- `$table->string('nome', 255)->index()->comment(...)`
- `$table->string('nif', 255)->index()->comment(...)`
- `$table->boolean('e_cliente')->default(false)->index()->comment(...)`
- `$table->boolean('e_fornecedor')->default(false)->index()->comment(...)`
- `$table->boolean('e_empresa_aplicacao')->default(false)->index()->comment(...)`
- `$table->timestamps()`
- `if (DB::getDriverName() === 'mysql') { DB::statement('CREATE UNIQUE INDEX unica_empresa_mae_idx ON entidades (e_empresa_aplicacao) WHERE (e_empresa_aplicacao = 1)'); }`

`down()`:
- `if (DB::getDriverName() === 'mysql') { DB::statement('DROP INDEX IF EXISTS unica_empresa_mae_idx ON entidades'); }`
- `Schema::dropIfExists('entidades')`

**Lint/Refactor:** `composer lint && composer refactor`

---

### T2 — Model `Entidade`

**Ficheiro:** `app/Models/Entidade.php`

Criar via `php artisan make:model Entidade --no-interaction` (apagar o stub e escrever do zero seguindo a Spec).

- `strict_types=1`
- `#[Table('entidades')]`, `#[Fillable([...])]`, `#[UsePolicy(EntidadePolicy::class)]`
- `use HasFactory, HasUuids`
- `@property-read` para todos os 8 campos (`string`, `bool`, `Carbon`)
- `casts()` com `'boolean'` nas 3 flags
- Scopes: `scopeWhereCliente`, `scopeWhereFornecedor`, `scopeWhereEmpresaAplicacao`

**Lint/Refactor:** `composer lint && composer refactor`

---

### T3 — Factory `EntidadeFactory`

**Ficheiro:** `database/factories/EntidadeFactory.php`

Criar via `php artisan make:factory EntidadeFactory --model=Entidade --no-interaction`.

- `definition()`: `nome` (faker company), `nif` (9 digitos numericos), flags todas `false`
- States: `cliente()`, `fornecedor()`, `clienteEFornecedor()`, `empresaAplicacao()`

**Lint/Refactor:** `composer lint && composer refactor`

---

### T4 — Policy `EntidadePolicy`

**Ficheiro:** `app/Policies/EntidadePolicy.php`

Criar via `php artisan make:policy EntidadePolicy --model=Entidade --no-interaction`.

- `strict_types=1`, `final class`
- `?User $utilizador` em todos os metodos
- `viewAny`, `view`, `create`, `update`, `delete` — todos retornam `true`

**Lint/Refactor:** `composer lint && composer refactor`

---

### T5 — Testes do Model

**Ficheiro:** `tests/Feature/Models/EntidadeTest.php`

Criar via `php artisan make:test --pest EntidadeModelTest --no-interaction`.

Testes (com `RefreshDatabase`):
- `casts boolean nas tres flags` — criar via factory, verificar `is_bool()`
- `fillable contem os campos esperados` — `getFillable()` === lista esperada
- `scope whereCliente retorna so clientes` — cria cliente + fornecedor, scope retorna 1
- `scope whereFornecedor retorna so fornecedores`
- `scope whereEmpresaAplicacao retorna so empresa mae`
- `scope whereCliente exclui nao-clientes`

**Lint/Refactor + testes:** `composer lint && composer refactor && composer test`

---

### T6 — Testes da Policy

**Ficheiro:** `tests/Feature/Policies/EntidadePolicyTest.php`

Criar via `php artisan make:test --pest EntidadePolicyTest --no-interaction`.

Testes (com `RefreshDatabase`):
- Utilizador autenticado pode: `viewAny`, `view`, `create`, `update`, `delete` (5 testes)
- Guest nao pode: `viewAny`, `view`, `create`, `update`, `delete` (5 testes)

Usar `Gate::forUser($utilizador)->allows(...)` e `Gate::forUser(null)->allows(...)`.

**Pipeline completa:** `composer test`

---

## Ordem de execucao

```
T1 (migration) → T2 (model) → T3 (factory) → T4 (policy) → T5 (testes model) → T6 (testes policy)
```

Cada tarefa termina com `composer lint && composer refactor`. A pipeline completa `composer test` corre no final de T5 e T6.

---

## Checkpoints obrigatorios

| Checkpoint | Momento |
|---|---|
| A | Brief aprovado (concluido) |
| B | Spec aprovada (concluido) |
| Por tarefa | Apos T1, T2, T3, T4 — mostrar diff e aguardar aprovacao |
| ② | Apos T5+T6 — mostrar resultado `composer test` |
| D | Debrief gerado |
| E | PR publicado |
