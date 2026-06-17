# Plano de Implementação — Issue #22

**Data:** 2026-06-17
**Spec:** `docs/specs/2026-06-17-corrigir-nomenclatura-categorias.md`
**Branch:** `feat/corrigir-nomenclatura-categorias`

---

## Tarefas

| # | Ficheiro | CAs |
|---|----------|-----|
| T1 | `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php` | CA-01, CA-02 |
| T2 | `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php` | CA-01, CA-02 |
| T3 | `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php` | CA-01 |
| T4 | `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php` | CA-01, CA-03 |
| T5 | `app/Features/CategoriaDocumento/CategoriaDocumentoController.php` | CA-02, CA-04 |
| T6 | `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaActionTest.php` | CA-05 |

## Ordem de execução

T1 → T2 → T3 → T4 → T5 → T6

DTOs primeiro porque as Actions e testes dependem dos nomes das propriedades.

## Verificação

```bash
composer lint
composer refactor
composer test
```

Pipeline deve passar sem erros.
