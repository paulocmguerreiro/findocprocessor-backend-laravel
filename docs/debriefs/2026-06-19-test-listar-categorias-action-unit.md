# Debrief — Issue #41: ListarCategoriasActionTest (Unit)

**Data:** 2026-06-19
**Issue:** #41
**Branch:** feat/test-listar-categorias-action-unit
**Duração:** ~15 minutos

---

## O que foi feito

Criado `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php` com 3 testes de invocação programática directa da `ListarCategoriasAction`, completando o padrão dual obrigatório para a feature `CategoriaDocumento`.

---

## Ficheiros criados

| Ficheiro | Tipo |
|---|---|
| `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php` | novo |

---

## Decisões tomadas

**Nenhum desvio ao padrão.** Implementação por analogia directa com `tests/Unit/Features/Entidade/ListarEntidadesActionTest.php` (referência explícita na issue).

A Policy `CategoriaDocumentoPolicy::viewAny()` retorna `true` para guests — não é necessário autenticar nos testes unit, mesmo que a Action chame `Gate::authorize()` internamente.

---

## Resultado dos testes

```
173 testes, 578 assertions — 100% passed
100% type coverage
100% code coverage (incluindo ListarCategoriasAction)
Rector: 0 changed files
Pint: passed
PHPStan nível 9: 0 erros
```

---

## Estado do padrão dual em `CategoriaDocumento`

| Action | Unit test | Feature test |
|---|---|---|
| `CriarCategoriaAction` | ✅ | ✅ |
| `VerCategoriaAction` | ✅ | ✅ |
| `ActualizarCategoriaAction` | ✅ | ✅ |
| `EliminarCategoriaAction` | ✅ | ✅ |
| `ListarCategoriasAction` | ✅ **← adicionado** | ✅ |

---

## Aprendizagens

**Padrão dual — separação de responsabilidades em prática:**

O teste unit da `ListarCategoriasAction` não testa o endpoint HTTP nem a serialização do Resource — testa apenas o comportamento da Action como unidade de lógica: o que devolve, em que ordem, com que paginação. Isso torna-o reutilizável como contrato para Jobs, Artisan ou qualquer outro caller que invoque a Action fora do contexto HTTP.

A distinção ficou clara ao comparar os dois ficheiros lado a lado: o Feature test (`ListarCategoriasTest.php`) usa `$this->getJson('/api/...')` e valida a estrutura JSON da resposta; o Unit test usa `(new ListarCategoriasAction)->handle(...)` e valida o `CursorPaginator` directamente — `count()`, `pluck('nome')`, `nextCursor()`. São dois níveis de confiança diferentes, complementares, nunca redundantes.
