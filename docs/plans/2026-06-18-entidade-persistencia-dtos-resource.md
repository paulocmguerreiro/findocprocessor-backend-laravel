# Plano — Issue #32: Entidade — persistence layer (DTOs + resource + testes)

**Data:** 2026-06-18
**Issue:** #32
**Branch:** `feat/entidade-persistencia-dtos-resource`

---

## Tarefas

### T1 — `CriarEntidadeDto`

**Ficheiro:** `app/Features/Entidade/Criar/CriarEntidadeDto.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Entidade\Criar;

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
        if (trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }
        if (trim($this->nif) === '') {
            throw new \InvalidArgumentException('nif não pode ser vazio.');
        }
    }
}
```

Verificação: `composer test:types` passa.

---

### T2 — `ActualizarEntidadeDto`

**Ficheiro:** `app/Features/Entidade/Actualizar/ActualizarEntidadeDto.php`

Estrutura idêntica a `CriarEntidadeDto` — copiar, ajustar namespace.

Verificação: `composer test:types` passa.

---

### T3 — `EntidadeResource`

**Ficheiro:** `app/Features/Entidade/EntidadeResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Features\Entidade;

use App\Models\Entidade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Entidade */
final class EntidadeResource extends JsonResource
{
    /**
     * @return array{id: string, nome: string, nif: string, e_cliente: bool, e_fornecedor: bool, e_empresa_aplicacao: bool}
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'nome'                => $this->nome,
            'nif'                 => $this->nif,
            'e_cliente'           => $this->e_cliente,
            'e_fornecedor'        => $this->e_fornecedor,
            'e_empresa_aplicacao' => $this->e_empresa_aplicacao,
        ];
    }
}
```

Verificação: `composer test:types` passa.

---

### T4 — Testes `CriarEntidadeDtoTest`

**Ficheiro:** `tests/Unit/Features/Entidade/CriarEntidadeDtoTest.php`

```
describe('Construtor') {
  lança InvalidArgumentException se nome for vazio ('')
  lança InvalidArgumentException se nome for só espaços ('   ')
  lança InvalidArgumentException se nif for vazio ('')
  lança InvalidArgumentException se nif for só espaços ('   ')
  cria DTO com dados válidos — verifica os 5 campos
}
```

Verificação: `composer test` passa.

---

### T5 — Testes `ActualizarEntidadeDtoTest`

**Ficheiro:** `tests/Unit/Features/Entidade/ActualizarEntidadeDtoTest.php`

Estrutura idêntica ao T4, usando `ActualizarEntidadeDto`.

Verificação: `composer test` passa.

---

### T6 — Testes `EntidadeResourceTest`

**Ficheiro:** `tests/Unit/Features/Entidade/EntidadeResourceTest.php`

```
describe('EntidadeResource') {
  retorna os 6 campos com os valores correctos — usar factory->make()
  não inclui timestamps
  e_cliente, e_fornecedor, e_empresa_aplicacao são bool
}
```

Usar `Entidade::factory()->make()` com estados da factory (`cliente()`, `fornecedor()`, `empresaAplicacao()`).

Verificação: `composer test` passa com 100% coverage e 100% type coverage.

---

### T7 — Pipeline completa

```bash
composer lint
composer refactor
composer test
```

Todos os erros corrigidos antes de commit.

---

### T8 — Commit

```bash
git add app/Features/Entidade/ tests/Unit/Features/Entidade/
git commit -m "feat(entidade): DTOs + Resource — Issue #32"
```

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8
```

T1 e T2 podem ser feitos em paralelo (estrutura idêntica).
T4 e T5 podem ser feitos em paralelo.
T7 bloqueia T8.

---

## Referências

- Padrão DTO: `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php`
- Padrão Resource: `app/Features/CategoriaDocumento/CategoriaDocumentoResource.php`
- Padrão testes DTO: `tests/Unit/Features/CategoriaDocumento/CriarCategoriaDtoTest.php`
- Padrão testes Resource: `tests/Unit/Features/CategoriaDocumento/CategoriaDocumentoResourceTest.php`
- Model: `app/Models/Entidade.php`
