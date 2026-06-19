# System Spec — Feature: Entidade

> `App\Features\Entidade\`

CRUD completo de entidades (clientes, fornecedores, Empresa Mãe/Aplicação). Inclui a regra de negócio de unicidade da Empresa Mãe e um endpoint dedicado para converter uma entidade em Empresa Mãe.

**Fluxo de dados:**
```
HTTP Request → FormRequest (autoriza + valida) → Controller (constrói DTO ou passa Entidade via RMB) → Action (autoriza + acede Model) → Controller (formata com EntidadeResource) → ApiResponse
```

**Decisão arquitectural:** Sem Repository — Eloquent directo nas Actions (CRUD simples, ≤ 1 query por `handle()`). A sub-action `RemoverMarcacaoEmpresaMaeAction` é invocada via `RegraUnicidadeEmpresaMae` (classe de domínio que encapsula o `if (eEmpresaAplicacao)`), sempre **dentro** da transação do caller — nunca abre transação própria. A invariante "Empresa Mãe implica cliente + fornecedor" está encapsulada no trait `ComFlagsEfectivosEmpresaMae` (usado nos DTOs).

**Autorização:** dupla verificação — FormRequest + Action. `RemoverMarcacaoEmpresaMaeAction` (action interna) não tem `Gate::authorize()` próprio — é chamada dentro de uma Action já autorizada.

---

## Actions

| Classe | Namespace | Assinatura `handle()` | Descrição |
|---|---|---|---|
| `ListarEntidadesAction` | `App\Features\Entidade\Listar` | `handle(int $perPage, CampoOrdenacaoEntidades $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator<int, Entidade>` | Devolve página via cursor pagination, ordenada pelo campo e direcção indicados |
| `CriarEntidadeAction` | `App\Features\Entidade\Criar` | `handle(CriarEntidadeDto): Entidade` | Cria entidade; invoca `RegraUnicidadeEmpresaMae` se `eEmpresaAplicacao = true` |
| `VerEntidadeAction` | `App\Features\Entidade\Ver` | `handle(Entidade\|string): Entidade` | Devolve entidade; resolve UUID com `findOrFail` se string |
| `ActualizarEntidadeAction` | `App\Features\Entidade\Actualizar` | `handle(Entidade\|string, ActualizarEntidadeDto): Entidade` | Update completo (PUT semântico); invoca `RegraUnicidadeEmpresaMae`; devolve `refresh()` |
| `EliminarEntidadeAction` | `App\Features\Entidade\Eliminar` | `handle(Entidade\|string): void` | Hard delete |
| `ConverterEmEmpresaMaeAction` | `App\Features\Entidade\EmpresaMae` | `handle(Entidade\|string): Entidade` | Remove marcação anterior + força os 3 flags (`e_empresa_aplicacao`, `e_cliente`, `e_fornecedor`) |
| `RemoverMarcacaoEmpresaMaeAction` | `App\Features\Entidade\EmpresaMae` | `handle(): void` | Action interna — `UPDATE entidades SET e_empresa_aplicacao = false WHERE e_empresa_aplicacao = true`; sem autorização própria; sempre chamada dentro da transação do caller |

---

## Classe de domínio

| Classe | Namespace | Descrição |
|---|---|---|
| `RegraUnicidadeEmpresaMae` | `App\Features\Entidade\EmpresaMae` | Encapsula a regra: se `eEmpresaAplicacao = true`, invoca `RemoverMarcacaoEmpresaMaeAction`. Injectada por construtor nas 3 Actions de escrita. |

---

## DTOs

Todos `final readonly`. Usam o trait `ComFlagsEfectivosEmpresaMae`. `fromRequest()` com array shape `@var` (Larastan nível 9). Construtor valida `nome`/`nif` não-vazios.

### `CriarEntidadeDto` — `App\Features\Entidade\Criar\CriarEntidadeDto`

```php
final readonly class CriarEntidadeDto
{
    /** @throws \InvalidArgumentException */
    public function __construct(
        public string $nome,
        public string $nif,
        public bool $eCliente,
        public bool $eFornecedor,
        public bool $eEmpresaAplicacao,
    ) {
        if (trim($this->nome) === '') { throw new \InvalidArgumentException('nome não pode ser vazio.'); }
        if (trim($this->nif) === '') { throw new \InvalidArgumentException('nif não pode ser vazio.'); }
    }
}
```

- Booleans sem validação — `bool` não tem estado "vazio"
- Invariante `eEmpresaAplicacao → eCliente/eFornecedor` pertence à Action (regra de negócio)

### `ActualizarEntidadeDto` — `App\Features\Entidade\Actualizar\ActualizarEntidadeDto`

Estrutura idêntica a `CriarEntidadeDto` — update completo (sem campos opcionais).

```php
final readonly class ActualizarEntidadeDto
{
    /** @throws \InvalidArgumentException */
    public function __construct(
        public string $nome,
        public string $nif,
        public bool $eCliente,
        public bool $eFornecedor,
        public bool $eEmpresaAplicacao,
    ) {
        if (trim($this->nome) === '') { throw new \InvalidArgumentException('nome não pode ser vazio.'); }
        if (trim($this->nif) === '') { throw new \InvalidArgumentException('nif não pode ser vazio.'); }
    }
}
```

- Campos não-nullable — update completo (PUT); construtor valida invariantes incondicionalmente

---

## Trait

| Classe | Namespace | Descrição |
|---|---|---|
| `ComFlagsEfectivosEmpresaMae` | `App\Features\Entidade` | `eClienteEfectivo(): bool` = `eEmpresaAplicacao || eCliente`; `eFornecedorEfectivo(): bool` = `eEmpresaAplicacao || eFornecedor`. Garante a invariante em criar e actualizar sem duplicação. |

---

## Enums de listagem

| Classe | Namespace | Cases | Descrição |
|---|---|---|---|
| `CampoOrdenacaoEntidades` | `App\Features\Entidade\Listar` | `Nome = 'nome'` | Campo de ordenação da listagem de entidades; extensível com `Nif`, `CreatedAt` |

---

## Policy

`EntidadePolicy` (`App\Policies`) — auto-descoberta por convenção de nome. Todos os métodos aceitam `?User $utilizador` (nullable — guests permitidos). Nesta fase, todos retornam `true`.

| Método | Assinatura |
|---|---|
| `viewAny` | `viewAny(?User $utilizador): bool` |
| `view` | `view(?User $utilizador, Entidade $entidade): bool` |
| `create` | `create(?User $utilizador): bool` |
| `update` | `update(?User $utilizador, Entidade $entidade): bool` |
| `delete` | `delete(?User $utilizador, Entidade $entidade): bool` |

---

## FormRequests

| Classe | Namespace | `authorize()` chama | `rules()` |
|---|---|---|---|
| `ListarEntidadesRequest` | `Listar` | `Gate::authorize('viewAny', Entidade::class)` | `per_page`, `sort`, `direction`, `cursor` |
| `CriarEntidadeRequest` | `Criar` | `Gate::authorize('create', Entidade::class)` | `nome`, `nif`, `e_cliente`, `e_fornecedor`, `e_empresa_aplicacao` (required) |
| `VerEntidadeRequest` | `Ver` | `Gate::authorize('view', $this->route('entidade'))` | `[]` |
| `ActualizarEntidadeRequest` | `Actualizar` | `Gate::authorize('update', $this->route('entidade'))` | `nome`, `nif`, `e_cliente`, `e_fornecedor`, `e_empresa_aplicacao` (required) |
| `EliminarEntidadeRequest` | `Eliminar` | `Gate::authorize('delete', $this->route('entidade'))` | `[]` |
| `ConverterEmEmpresaMaeRequest` | `EmpresaMae` | `Gate::authorize('update', $this->route('entidade'))` | `[]` |

`CriarEntidadeRequest` e `ActualizarEntidadeRequest` não são `final` (mockáveis em testes unitários de DTO).

---

## Controller

`EntidadeController` (`App\Features\Entidade`) — `final`, sem lógica. Usa Route Model Binding (`Entidade $entidade`) + injecção de Actions via parâmetros de método.

| Método | FormRequest | Action invocada |
|---|---|---|
| `index` | `ListarEntidadesRequest` | `ListarEntidadesAction::handle()` — extrai `per_page` (cast `int`), `sort`, `direction`; devolve via `ApiResponse::devolverPaginado()` |
| `store` | `CriarEntidadeRequest` | `CriarEntidadeAction::handle(CriarEntidadeDto::fromRequest($pedido))` |
| `show` | `VerEntidadeRequest` | `VerEntidadeAction::handle($entidade)` |
| `update` | `ActualizarEntidadeRequest` | `ActualizarEntidadeAction::handle($entidade, ActualizarEntidadeDto::fromRequest($pedido))` |
| `destroy` | `EliminarEntidadeRequest` | `EliminarEntidadeAction::handle($entidade)` |
| `converterEmEmpresaMae` | `ConverterEmEmpresaMaeRequest` | `ConverterEmEmpresaMaeAction::handle($entidade)` |

---

## Resource

`EntidadeResource` — `App\Features\Entidade\EntidadeResource`

Formata a resposta JSON de todos os endpoints que retornem uma `Entidade`.

```json
{
  "id": "019741b2-...",
  "nome": "Empresa Teste",
  "nif": "123456789",
  "e_cliente": true,
  "e_fornecedor": true,
  "e_empresa_aplicacao": false
}
```

- Booleans devolvidos como `bool` (cast Eloquent `'boolean'` garante o tipo)
- Timestamps omitidos intencionalmente
- PHPDoc `array{id: string, nome: string, nif: string, e_cliente: bool, e_fornecedor: bool, e_empresa_aplicacao: bool}` em `toArray()`
- `@mixin Entidade` necessário para Larastan inferir as propriedades do model
