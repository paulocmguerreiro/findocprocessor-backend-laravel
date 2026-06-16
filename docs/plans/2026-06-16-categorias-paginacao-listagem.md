# Plano — Issue #9: Paginação na listagem de categorias

**Data:** 2026-06-16
**Issue:** [#9](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/9)
**Slug:** categorias-paginacao-listagem
**Branch:** feat/categorias-paginacao-listagem

---

## Tarefas

### T1 — Enum `CampoOrdenacaoCategorias`

**Ficheiro:** `app/Features/CategoriaDocumento/Listar/CampoOrdenacaoCategorias.php`

Criar enum backed string com `case Nome = 'nome'`.

```php
<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Listar;

enum CampoOrdenacaoCategorias: string
{
    case Nome = 'nome';
}
```

**Verificação:** `composer lint && composer refactor`

---

### T2 — `ListarCategoriasRequest`

**Ficheiro:** `app/Features/CategoriaDocumento/Listar/ListarCategoriasRequest.php`

FormRequest com `authorize(): true`, rules para `per_page`, `sort`, `cursor`, mensagens em PT.

- `per_page`: `['sometimes', 'integer', 'min:1', 'max:100']`
- `sort`: `['sometimes', 'string', Rule::in(array_column(CampoOrdenacaoCategorias::cases(), 'value'))]`
- `cursor`: `['sometimes', 'string']`

**Verificação:** `composer lint && composer refactor`

---

### T3 — Alterar `ListarCategoriasAction`

**Ficheiro:** `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php`

Mudar assinatura e body:

```php
/** @return \Illuminate\Pagination\CursorPaginator<int, CategoriaDocumento> */
public function handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao): \Illuminate\Pagination\CursorPaginator
{
    return CategoriaDocumento::orderBy($campoOrdenacao->value)
        ->cursorPaginate($perPage);
}
```

Remover import de `Collection`. Adicionar import de `CampoOrdenacaoCategorias`.

**Verificação:** `composer lint && composer refactor`

---

### T4 — Novo método `ApiResponse::devolverPaginado()`

**Ficheiro:** `app/Shared/Http/ApiResponse.php`

Adicionar método:

```php
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

public static function devolverPaginado(AnonymousResourceCollection $coleccao): JsonResponse
{
    return $coleccao->response();
}
```

**Verificação:** `composer lint && composer refactor`

---

### T5 — Alterar `CategoriaDocumentoController::index()`

**Ficheiro:** `app/Features/CategoriaDocumento/CategoriaDocumentoController.php`

- Substituir parâmetro `ListarCategoriasAction $accao` por `ListarCategoriasRequest $pedido, ListarCategoriasAction $accao`
- Ler `per_page` e `sort` do validated com defaults
- Construir `CampoOrdenacaoCategorias` via `::from()`
- Chamar `ApiResponse::devolverPaginado()` em vez de `devolverColeccao()`

```php
public function index(ListarCategoriasRequest $pedido, ListarCategoriasAction $accao): JsonResponse
{
    /** @var array{per_page?: int, sort?: string} $validated */
    $validated = $pedido->validated();

    $porPagina      = $validated['per_page'] ?? 15;
    $campoOrdenacao = CampoOrdenacaoCategorias::from($validated['sort'] ?? CampoOrdenacaoCategorias::Nome->value);

    $categorias = $accao->handle($porPagina, $campoOrdenacao);

    return ApiResponse::devolverPaginado(
        CategoriaDocumentoResource::collection($categorias),
    );
}
```

Adicionar imports: `ListarCategoriasRequest`, `CampoOrdenacaoCategorias`.

**Verificação:** `composer lint && composer refactor`

---

### T6 — Actualizar `ListarCategoriasTest`

**Ficheiro:** `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php`

Substituir os dois testes existentes e adicionar novos cenários:

| Teste | Verifica |
|---|---|
| lista vazia | 200, `data=[]`, `links.next=null`, `meta.per_page=15` |
| lista com registos (estrutura) | 200, `assertJsonStructure` com `data.[*]`, `links.[prev,next]`, `meta.[per_page,next_cursor,prev_cursor,path]` |
| paginação por `per_page` | 5 registos, `per_page=2` → `data` com 2 items, `links.next` não nulo |
| navegar via cursor (next) | cursor de `links.next` devolve página seguinte sem duplicados |
| `per_page` acima do máximo | 422, erro em `errors.per_page` |
| `sort` inválido | 422, erro em `errors.sort` |
| cursor além do fim | 200, `data=[]`, `links.next=null` |

**Verificação:** `composer test`

---

### T7 — Pipeline completa

```bash
composer test
```

Zero erros em lint, refactor, tipos, type-coverage e testes.

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7
```

Cada tarefa termina com `composer lint && composer refactor` antes de avançar. T7 corre a pipeline completa no fim.

---

## Notas de implementação

- O cursor activo (`?cursor=...`) é resolvido automaticamente pelo Laravel a partir do Request corrente — não é necessário passá-lo explicitamente à Action
- `CursorPaginator` não expõe `total` nem `last_page` — os testes não devem assertar essas chaves
- `ApiResponse::devolverColeccao()` mantém-se inalterado — continua disponível para outros usos futuros
- O brief actualizado inclui a mudança de `paginate` para `cursorPaginate` como decisão arquitectural do sistema
