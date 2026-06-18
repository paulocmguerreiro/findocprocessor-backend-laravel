# Spec — Issue #32: Entidade — persistence layer (DTOs + resource + testes)

**Data:** 2026-06-18
**Issue:** #32
**Branch:** `feat/entidade-persistencia-dtos-resource`

---

## Contratos

### `CriarEntidadeDto`

```
Namespace:  App\Features\Entidade\Criar
Ficheiro:   app/Features/Entidade/Criar/CriarEntidadeDto.php
Modificador: final readonly class
```

**Construtor:**

| Parâmetro | Tipo | Validação |
|---|---|---|
| `$nome` | `string` | `trim($this->nome) === ''` → `\InvalidArgumentException('nome não pode ser vazio.')` |
| `$nif` | `string` | `trim($this->nif) === ''` → `\InvalidArgumentException('nif não pode ser vazio.')` |
| `$eCliente` | `bool` | sem validação |
| `$eFornecedor` | `bool` | sem validação |
| `$eEmpresaAplicacao` | `bool` | sem validação |

**Sem `fromRequest()`** — adicionado na issue de lógica.

**PHPDoc obrigatório:**
- `@throws \InvalidArgumentException` no construtor

---

### `ActualizarEntidadeDto`

```
Namespace:  App\Features\Entidade\Actualizar
Ficheiro:   app/Features/Entidade/Actualizar/ActualizarEntidadeDto.php
Modificador: final readonly class
```

Estrutura idêntica a `CriarEntidadeDto` — update completo (sem campos opcionais).

| Parâmetro | Tipo | Validação |
|---|---|---|
| `$nome` | `string` | `trim($this->nome) === ''` → `\InvalidArgumentException('nome não pode ser vazio.')` |
| `$nif` | `string` | `trim($this->nif) === ''` → `\InvalidArgumentException('nif não pode ser vazio.')` |
| `$eCliente` | `bool` | sem validação |
| `$eFornecedor` | `bool` | sem validação |
| `$eEmpresaAplicacao` | `bool` | sem validação |

**Sem `fromRequest()`** — adicionado na issue de lógica.

---

### `EntidadeResource`

```
Namespace:  App\Features\Entidade
Ficheiro:   app/Features/Entidade/EntidadeResource.php
Herança:    Illuminate\Http\Resources\Json\JsonResource
Modificador: final class
```

**`toArray(Request $request): array`**

PHPDoc array shape:
```php
/** @return array{id: string, nome: string, nif: string, e_cliente: bool, e_fornecedor: bool, e_empresa_aplicacao: bool} */
```

Mapeamento:

| Chave JSON | Fonte no Model | Tipo |
|---|---|---|
| `id` | `$this->id` | `string` |
| `nome` | `$this->nome` | `string` |
| `nif` | `$this->nif` | `string` |
| `e_cliente` | `$this->e_cliente` | `bool` |
| `e_fornecedor` | `$this->e_fornecedor` | `bool` |
| `e_empresa_aplicacao` | `$this->e_empresa_aplicacao` | `bool` |

`created_at` e `updated_at` omitidos.

`@mixin Entidade` obrigatório (Larastan).

---

## Testes

### `tests/Unit/Features/Entidade/CriarEntidadeDtoTest.php`

```
describe('Construtor') {
  it lança InvalidArgumentException se nome for vazio
  it lança InvalidArgumentException se nome for só espaços (trim)
  it lança InvalidArgumentException se nif for vazio
  it lança InvalidArgumentException se nif for só espaços (trim)
  it cria DTO com dados válidos — verifica todos os 5 campos
}
```

Sem `describe('fromRequest()')` — sem FormRequest nesta fase.

### `tests/Unit/Features/Entidade/ActualizarEntidadeDtoTest.php`

Estrutura idêntica ao `CriarEntidadeDtoTest`:

```
describe('Construtor') {
  it lança InvalidArgumentException se nome for vazio
  it lança InvalidArgumentException se nome for só espaços (trim)
  it lança InvalidArgumentException se nif for vazio
  it lança InvalidArgumentException se nif for só espaços (trim)
  it cria DTO com dados válidos — verifica todos os 5 campos
}
```

### `tests/Unit/Features/Entidade/EntidadeResourceTest.php`

```
describe('EntidadeResource') {
  it retorna os 6 campos com os valores correctos
  it não inclui timestamps
  it e_cliente, e_fornecedor, e_empresa_aplicacao são bool
}
```

Usar `Entidade::factory()->make(...)` — sem persistência em DB.
Usar estados da factory: `cliente()`, `fornecedor()`, `empresaAplicacao()`.

---

## Critérios de aceitação mapeados

| CA | Cobertura |
|---|---|
| CA-01 | `CriarEntidadeDto` é `final readonly class` |
| CA-02 | `ActualizarEntidadeDto` é `final readonly class` |
| CA-03 | Construtor lança `\InvalidArgumentException` para `nome`/`nif` vazios |
| CA-04 | `EntidadeResource` serializa os 6 campos (sem timestamps) |
| CA-05 | Testes happy path para criação e actualização |
| CA-06 | Testes `nome` vazio → `\InvalidArgumentException` |
| CA-07 | Testes `nif` vazio → `\InvalidArgumentException` |
| CA-08 | Testes serialização Resource (campos + tipos) |
| CA-09 | `composer test` passa (100% coverage + 100% type coverage) |

---

## System spec a actualizar (Fase 3)

| Ficheiro | Conteúdo a adicionar |
|---|---|
| `docs/system_spec/02-shared.md` | Secção DTOs para `Entidade` (`CriarEntidadeDto`, `ActualizarEntidadeDto`) + Resource |
