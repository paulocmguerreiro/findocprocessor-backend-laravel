# Debrief — Issue #9: Paginação na listagem de categorias

**Data:** 2026-06-16
**Issue:** [#9](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/9)
**Slug:** categorias-paginacao-listagem
**Branch:** feat/categorias-paginacao-listagem
**Duração estimada:** 1 sessão

---

## O que foi implementado

Cursor-based pagination na listagem de categorias de documento. A `ListarCategoriasAction` passou de `CategoriaDocumento::all()` (sem tecto) para `cursorPaginate()` com ordenação configurável via query params.

### Ficheiros criados

| Ficheiro | Descrição |
|---|---|
| `app/Features/CategoriaDocumento/Listar/CampoOrdenacaoCategorias.php` | Enum backed string `CampoOrdenacaoCategorias` com `case Nome = 'nome'` |
| `app/Features/CategoriaDocumento/Listar/ListarCategoriasRequest.php` | FormRequest com validação de `per_page`, `sort`, `direction`, `cursor` e mensagens em PT |
| `app/Shared/Enums/DirecaoOrdenacao.php` | Enum partilhado `DirecaoOrdenacao` com `Asc` e `Desc` — reutilizável em listagens futuras |

### Ficheiros alterados

| Ficheiro | O que mudou |
|---|---|
| `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php` | Assinatura: `(): Collection` → `(int, CampoOrdenacaoCategorias, DirecaoOrdenacao): CursorPaginator`; body: `::all()` → `::orderBy(...)->cursorPaginate()` |
| `app/Features/CategoriaDocumento/CategoriaDocumentoController.php` | `index()` injeta `ListarCategoriasRequest`, extrai e converte `per_page`/`sort`/`direction`, chama `ApiResponse::devolverPaginado()` |
| `app/Shared/Http/ApiResponse.php` | Novo método `devolverPaginado(AnonymousResourceCollection): JsonResponse` |
| `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php` | Testes completos de cursor pagination: estrutura, per_page, navegação via cursor, validações, cursor além do fim |

---

## Decisões tomadas

### 1. Cursor pagination (keyset) em vez de OFFSET

O brief inicial mencionava `paginate()` com `page`/`per_page`. Durante o planeamento da Fase 1, a decisão foi revertida para `cursorPaginate()` com base no custo de O(n) do OFFSET vs. O(log n) do keyset.

**Trade-off aceite:** `meta.total` e `meta.last_page` não estão disponíveis — COUNT(\*) seria também O(n). A navegação é apenas `next`/`prev` via cursor opaco.

### 2. `DirecaoOrdenacao` como enum partilhado

O param `direction` não estava na spec original — foi adicionado durante a implementação da T3/T5. Colocado em `App\Shared\Enums` (não dentro da slice) porque é um contrato genérico reutilizável em todas as listagens futuras.

### 3. Cast explícito `(int) $validated['per_page']`

O `validated()` devolve `string` para query params mesmo com a rule `integer` — o Larastan inferiu `mixed`. A solução foi `isset($validated['per_page']) ? (int) $validated['per_page'] : 15` em vez de `$validated['per_page'] ?? 15` para garantir tipagem correcta e satisfazer o Larastan nível 9.

### 4. Cursor resolvido automaticamente pelo Laravel

Não é necessário passar o cursor explicitamente à Action. O `cursorPaginate()` lê o query param `cursor` directamente do Request corrente via `Illuminate\Http\Request::input()`. O Controller apenas passa `per_page`, `sort` e `direction`.

---

## Desvios em relação ao plano

| Desvio | Razão |
|---|---|
| `direction` adicionado (não estava na spec) | Necessário para ordenação descendente; adicionado ao enum partilhado para reutilização |
| `per_page` cast com `(int)` no controller | `validated()` retorna `string`; necessário para satisfazer Larastan nível 9 |
| Spec inicial com `paginate()` | Revertido para `cursorPaginate()` no Brief — decisão arquitectural de sistema |

---

## Resultado final

- **Testes:** 68 passed, 211 assertions, 100% cobertura
- **Larastan:** 0 erros (nível 9)
- **Rector:** 0 sugestões
- **Pint:** formatação aplicada
- **Pipeline `composer test`:** verde

---

## Aprendizagens

### Cursor pagination — o cursor não é um parâmetro da Action

O insight mais importante desta issue: `cursorPaginate()` resolve o cursor automaticamente do `Request` corrente via `Illuminate\Http\Request::input('cursor')`. Isso significa que a Action **não precisa de receber o cursor como parâmetro** — basta receber `$perPage`, `$campoOrdenacao` e `$direcaoOrdenacao`. O Laravel injeta o cursor por baixo.

Em termos de Vertical Slice: a Action fica limpa (sem dependência do HTTP Request), e o Controller mantém a responsabilidade de extrair e validar o `per_page` e os enums de ordenação.

### Enums partilhados vs. enums de slice

`CampoOrdenacaoCategorias` é específico da slice `Listar` e vive em `App\Features\CategoriaDocumento\Listar`. Mas `DirecaoOrdenacao` (`asc`/`desc`) é um conceito ortogonal a qualquer ordenação no sistema — vai ao `App\Shared\Enums`. A linha de corte: **se o enum modela um conceito de domínio da feature** → slice; **se modela uma mecânica de query genérica** → shared.

### `validated()` devolve `string` para query params

O FormRequest valida com rule `integer` mas o resultado de `validated()` continua `string` para query params vindos do URL. Isso não é um bug — PHP não tem tipos nas query strings. A consequência é que o Larastan infere `mixed` se não houver `@var` com array shape, e o cast `(int)` é obrigatório no controller antes de passar à Action.
