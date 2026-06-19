# Spec — Issue #40: Entidade Feature Slice

**Data:** 2026-06-19
**Issue:** #40
**Slug:** `entidade-feature-slice`
**Branch:** `feat/entidade-feature-slice`

---

## Contratos de interface

### Enum `CampoOrdenacaoEntidades`

```php
namespace App\Features\Entidade\Listar;

enum CampoOrdenacaoEntidades: string
{
    case Nome = 'nome';
}
```

---

### Action `ListarEntidadesAction`

```php
public function handle(
    int $perPage,
    CampoOrdenacaoEntidades $campoOrdenacao,
    DirecaoOrdenacao $direcaoOrdenacao
): CursorPaginator  // CursorPaginator<int, Entidade>
```

- `Gate::authorize('viewAny', Entidade::class)` — fora de transação (leitura)
- `Entidade::orderBy(...)->cursorPaginate($perPage)`
- Sem `DB::transaction()` (leitura)

---

### Action `CriarEntidadeAction`

```php
public function handle(CriarEntidadeDto $dados): Entidade
// @throws AuthorizationException
// @throws \Throwable
```

- `Gate::authorize('create', Entidade::class)` — **fora** da transação
- `DB::transaction()`:
  - Se `$dados->eEmpresaAplicacao === true` → `RemoverMarcacaoEmpresaMaeAction::handle()`
  - `Entidade::create([...])` — se `eEmpresaAplicacao`, força `e_cliente = true`, `e_fornecedor = true`

---

### Action `VerEntidadeAction`

```php
public function handle(Entidade|string $idEntidade): Entidade
// @throws ModelNotFoundException
// @throws AuthorizationException
```

- Resolve UUID com `findOrFail` se string
- `Gate::authorize('view', $entidade)`
- Sem `DB::transaction()` (leitura)

---

### Action `ActualizarEntidadeAction`

```php
public function handle(Entidade|string $idEntidade, ActualizarEntidadeDto $dados): Entidade
// @throws ModelNotFoundException
// @throws AuthorizationException
// @throws \Throwable
```

- Resolve UUID se string
- `Gate::authorize('update', $entidade)` — **fora** da transação
- `DB::transaction()`:
  - Se `$dados->eEmpresaAplicacao === true` → `RemoverMarcacaoEmpresaMaeAction::handle()`
  - `$entidade->fill([...])->save()` — se `eEmpresaAplicacao`, força `e_cliente = true`, `e_fornecedor = true`
  - `$entidade->refresh()`
  - Devolve `$entidade`

---

### Action `EliminarEntidadeAction`

```php
public function handle(Entidade|string $idEntidade): void
// @throws ModelNotFoundException<Entidade>
// @throws AuthorizationException
// @throws \Throwable
```

- Resolve UUID se string
- `Gate::authorize('delete', $entidade)` — **fora** da transação
- `DB::transaction(fn () => $entidade->delete())`

---

### Action `RemoverMarcacaoEmpresaMaeAction` (interna)

```php
public function handle(): void
// @throws \Throwable  (propaga da transação do caller)
```

- **Sem `Gate::authorize()`** — chamada dentro de Action já autorizada
- **Sem `DB::transaction()` própria** — executa dentro da transação do caller
- `Entidade::whereEmpresaAplicacao()->update(['e_empresa_aplicacao' => false])`

---

### Action `ConverterEmEmpresaMaeAction`

```php
public function handle(Entidade|string $idEntidade): Entidade
// @throws ModelNotFoundException
// @throws AuthorizationException
// @throws \Throwable
```

- Resolve UUID se string
- `Gate::authorize('update', $entidade)` — **fora** da transação
- `DB::transaction()`:
  1. `RemoverMarcacaoEmpresaMaeAction::handle()`
  2. `$entidade->update(['e_empresa_aplicacao' => true, 'e_cliente' => true, 'e_fornecedor' => true])`
  3. `$entidade->refresh()`
  4. Devolve `$entidade`

---

### FormRequests

#### `ListarEntidadesRequest`
- `authorize()`: `Gate::authorize('viewAny', Entidade::class)` → `return true`
- `rules()`: `per_page` (sometimes, integer, 1–100), `sort` (sometimes, Rule::in CampoOrdenacaoEntidades), `direction` (sometimes, Rule::in DirecaoOrdenacao), `cursor` (sometimes, string)
- `messages()`: mensagens em PT

#### `CriarEntidadeRequest`
- `authorize()`: `Gate::authorize('create', Entidade::class)` → `return true`
- `rules()`: `nome` (required, string, max:255), `nif` (required, string, max:20), `e_cliente` (required, boolean), `e_fornecedor` (required, boolean), `e_empresa_aplicacao` (required, boolean)
- `messages()`: mensagens em PT
- Não é `final` (mockável em testes unitários de DTO)

#### `VerEntidadeRequest`
- `authorize()`: `Gate::authorize('view', $this->route('entidade'))` → `return true`
- `rules()`: `[]`
- `final`

#### `ActualizarEntidadeRequest`
- `authorize()`: `Gate::authorize('update', $this->route('entidade'))` → `return true`
- `rules()`: mesmos campos que Criar (semântica PUT — todos required, sem `sometimes`)
- `messages()`: mensagens em PT
- Não é `final` (mockável em testes unitários de DTO)

#### `EliminarEntidadeRequest`
- `authorize()`: `Gate::authorize('delete', $this->route('entidade'))` → `return true`
- `rules()`: `[]`
- `final`

#### `ConverterEmEmpresaMaeRequest`
- `authorize()`: `Gate::authorize('update', $this->route('entidade'))` → `return true`
- `rules()`: `[]`
- `final`

---

### DTOs — `fromRequest()` a adicionar

#### `CriarEntidadeDto::fromRequest(CriarEntidadeRequest $request): self`

```php
/** @var array{nome: string, nif: string, e_cliente: bool, e_fornecedor: bool, e_empresa_aplicacao: bool} $dadosValidados */
$dadosValidados = $request->validated();

return new self(
    nome: $dadosValidados['nome'],
    nif: $dadosValidados['nif'],
    eCliente: (bool) $dadosValidados['e_cliente'],
    eFornecedor: (bool) $dadosValidados['e_fornecedor'],
    eEmpresaAplicacao: (bool) $dadosValidados['e_empresa_aplicacao'],
);
```

#### `ActualizarEntidadeDto::fromRequest(ActualizarEntidadeRequest $request): self`

Idêntico, usando `ActualizarEntidadeRequest`.

---

### Controller `EntidadeController`

```php
final class EntidadeController extends Controller
```

| Método | FormRequest | Action | Resposta |
|---|---|---|---|
| `index` | `ListarEntidadesRequest` | `ListarEntidadesAction` | `ApiResponse::devolverPaginado()` |
| `store` | `CriarEntidadeRequest` | `CriarEntidadeAction` | `ApiResponse::devolverCriado()` — 201 |
| `show` | `VerEntidadeRequest` | `VerEntidadeAction` | `ApiResponse::devolverSucesso()` — 200 |
| `update` | `ActualizarEntidadeRequest` | `ActualizarEntidadeAction` | `ApiResponse::devolverSucesso()` — 200 |
| `destroy` | `EliminarEntidadeRequest` | `EliminarEntidadeAction` | `ApiResponse::devolverVazio()` — 204 |
| `converterEmEmpresaMae` | `ConverterEmEmpresaMaeRequest` | `ConverterEmEmpresaMaeAction` | `ApiResponse::devolverSucesso()` — 200 |

Route Model Binding: parâmetro `{entidade}` → `Entidade $entidade` (resolvido via `HasUuids`).

---

### Rotas

```php
// routes/api.php
Route::apiResource('entidades', EntidadeController::class);
Route::patch('entidades/{entidade}/empresa-mae', [EntidadeController::class, 'converterEmEmpresaMae']);
```

---

## Testes de feature — contratos

### `CriarEntidadeTest`
- 201 com recurso ao criar com dados válidos
- 201 com `e_empresa_aplicacao = true` → remove marcação da anterior; persiste `e_cliente = true`, `e_fornecedor = true`
- 422 quando campos obrigatórios em falta
- 422 quando `nif` em branco
- 403 — (nesta fase policy retorna true, não testável de forma significativa; incluir guest pode criar)

### `ListarEntidadesTest`
- 200 lista vazia quando sem entidades
- 200 com estrutura correcta (id, nome, nif, e_cliente, e_fornecedor, e_empresa_aplicacao)
- Respeita `per_page`; `links.next` preenchido
- Navegação cursor sem duplicados
- 422 `per_page` > 100
- 422 `sort` inválido
- 422 `direction` inválida

### `VerEntidadeTest`
- 200 com recurso quando entidade existe
- 404 quando UUID não existe

### `ActualizarEntidadeTest`
- 200 com recurso actualizado
- 200 com `e_empresa_aplicacao = true` → remove marcação da anterior; persiste os 3 flags
- 404 quando UUID não existe
- 422 quando campos obrigatórios em falta

### `EliminarEntidadeTest`
- 204 ao eliminar entidade existente
- 404 quando UUID não existe

### `ConverterEmEmpresaMaeTest`
- 200 com entidade convertida → `e_empresa_aplicacao = true`, `e_cliente = true`, `e_fornecedor = true`
- Remove `e_empresa_aplicacao` da entidade anterior
- 404 quando UUID não existe

---

## Invariantes

- `EntidadeController` sem lógica — nunca `if`, nunca query directa
- `RemoverMarcacaoEmpresaMaeAction` executa **sempre** dentro da transação do caller — nunca abre transação própria
- `Gate::authorize()` **sempre fora** de `DB::transaction()` nas Actions de escrita
- Listagem usa `cursorPaginate()` — nunca `paginate()` com OFFSET
- `strict_types=1` em todos os ficheiros
- `@throws \Throwable` em todos os `handle()` que usam `DB::transaction()`
