# Plano — Issue #12: assertJsonStructure nos testes de listagem

**Data:** 2026-06-17
**Branch:** feat/assertjsonstructure-listagem-categorias
**Spec:** docs/specs/2026-06-17-assertjsonstructure-listagem-categorias.md

---

## Tarefas

### T1 — Envelope sem items: `'devolve lista vazia quando não existem categorias'`
**Ficheiro:** `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php`
**Operação:** Adicionar `assertJsonStructure` ao chain de assertions (envelope sem items em `data`).

### T2 — Envelope + items: `'respeita o parâmetro per_page na paginação'`
**Ficheiro:** `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php`
**Operação:** Adicionar `assertJsonStructure` com items ao chain de `$resposta`.

### T3 — Envelope + items em ambas as páginas: `'navega para a página seguinte via cursor sem duplicados'`
**Ficheiro:** `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php`
**Operação:** Adicionar `assertJsonStructure` a `$pagina1` e `$pagina2`.

### T4 — Envelope sem items: `'cursor além do fim devolve lista vazia'`
**Ficheiro:** `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php`
**Operação:** Adicionar `assertJsonStructure` ao chain de assertions (envelope sem items).

### T5 — Verificar pipeline
**Comando:** `composer test`
**Critério:** zero erros, zero falhas.

### T6 — Commit
```bash
git add tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php
git commit -m "test(categorias): adicionar assertJsonStructure nos testes de listagem — Issue #12"
```

---

## Ordem de execução

T1 → T2 → T3 → T4 → T5 → T6

Todas as tarefas são no mesmo ficheiro; sem dependências entre T1–T4.

---

## Critérios de conclusão

- [ ] CA-01: assertJsonStructure com items em `data` presente nos testes relevantes
- [ ] CA-02: assertJsonStructure com envelope (`links`, `meta` cursor pagination) presente nos testes relevantes
- [ ] CA-03: `composer test` verde
