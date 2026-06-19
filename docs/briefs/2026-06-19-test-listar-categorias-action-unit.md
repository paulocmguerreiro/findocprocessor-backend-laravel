# Brief — Issue #41: ListarCategoriasActionTest (Unit)

**Data:** 2026-06-19
**Issue:** #41
**Slug:** test-listar-categorias-action-unit
**Branch:** feat/test-listar-categorias-action-unit
**Tipo:** chore / test

---

## Contexto

A feature `CategoriaDocumento` implementa CRUD completo com padrão dual de testes: unit test (invocação programática da Action) + feature test (HTTP). As Actions Criar, Ver, Actualizar e Eliminar têm ambos os testes. A `ListarCategoriasAction` tem apenas o teste HTTP (`tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php`) — falta o teste unit programático.

O padrão de referência foi estabelecido na Issue #40 com `ListarEntidadesActionTest.php`.

---

## O que existe

| Ficheiro | Estado |
|---|---|
| `app/Features/CategoriaDocumento/Listar/ListarCategoriasAction.php` | ✅ implementado |
| `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php` | ✅ implementado |
| `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php` | ❌ em falta |

---

## O que fazer

Criar `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php` com:
- lista vazia quando sem categorias
- devolve categorias ordenadas por campo e direcção (ascendente)
- respeita per_page (cursor pagination — `nextCursor()` não nulo)

Seguir exactamente o padrão de `tests/Unit/Features/Entidade/ListarEntidadesActionTest.php`.

---

## Assinatura da Action

```php
public function handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator
```

---

## Riscos identificados

- Nenhum risco técnico — implementação directa por analogia com `ListarEntidadesActionTest`.
- A Action chama `Gate::authorize('viewAny', ...)` — mas como os testes correm com `RefreshDatabase` e a Policy retorna `true` para guests, não é necessário autenticar.

---

## Questões em aberto

- Nenhuma.

---

## Decisão arquitectural

Sem desvio do padrão. Testar apenas o comportamento programático da Action — sem HTTP, sem controller. Os testes HTTP já existem e cobrem a superfície pública.
