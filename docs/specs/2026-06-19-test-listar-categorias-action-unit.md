# Spec — Issue #41: ListarCategoriasActionTest (Unit)

**Data:** 2026-06-19
**Issue:** #41
**Slug:** test-listar-categorias-action-unit

---

## Ficheiro a criar

### `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php`

**Localização:** `tests/Unit/Features/CategoriaDocumento/`
**Pattern:** invocação directa da Action (sem HTTP), `uses(RefreshDatabase::class)`

#### Testes

| # | Descrição | Asserção |
|---|---|---|
| 1 | `devolve lista vazia quando não existem categorias` | `$resultado->count() === 0` |
| 2 | `devolve categorias ordenadas por nome ascendente` | `pluck('nome')->all() === ['Alfa', 'Beta', 'Zeta']` |
| 3 | `respeita o per_page na paginação por cursor` | `count === 2` e `nextCursor() !== null` |

#### Invocação

```php
(new ListarCategoriasAction)->handle(
    perPage: 15,
    campoOrdenacao: CampoOrdenacaoCategorias::Nome,
    direcaoOrdenacao: DirecaoOrdenacao::Asc,
)
```

#### Imports necessários

```php
use App\Features\CategoriaDocumento\Listar\CampoOrdenacaoCategorias;
use App\Features\CategoriaDocumento\Listar\ListarCategoriasAction;
use App\Models\CategoriaDocumento;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
```

---

## Critérios de aceitação

- [ ] 3 testes presentes no ficheiro unit
- [ ] `composer test` verde (100% coverage mantida)
- [ ] Sem alterações a código de produção
