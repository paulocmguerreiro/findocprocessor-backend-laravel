# Plano — Issue #16: refactor(categorias): @var array shape + @throws nos DTOs

**Data:** 2026-06-15
**Issue:** #16
**Branch:** refactor/dto-phpdoc-categorias
**Spec:** [2026-06-15-refactor-dto-phpdoc-categorias.md](../specs/2026-06-15-refactor-dto-phpdoc-categorias.md)

---

## Tarefas

### T1 — Anotar `CriarCategoriaDto::fromRequest()`

**Ficheiro:** `app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php`

1. Adicionar PHPDoc com `@throws \UnexpectedValueException` ao método `fromRequest()`
2. Adicionar `/** @var array{nome: string, slug: string, tipo_movimento: string} $validated */` antes de `$validated = $request->validated();`

**Verificação:** `composer test:types` sem erros

---

### T2 — Anotar `ActualizarCategoriaDto::fromRequest()`

**Ficheiro:** `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php`

1. Adicionar PHPDoc com `@throws \UnexpectedValueException` ao método `fromRequest()`
2. Adicionar `/** @var array{nome?: string, slug?: string, tipo_movimento?: string} $validated */` antes de `$validated = $request->validated();`

**Verificação:** `composer test:types` sem erros

---

### T3 — Pipeline de qualidade

```bash
composer lint       # Pint — formatação
composer refactor   # Rector — modernizações
composer test       # Pipeline completa (types + arch + coverage)
```

Corrigir eventuais erros antes de commitar.

---

### T4 — Commit

```bash
git add app/Features/CategoriaDocumento/Criar/CriarCategoriaDto.php
git add app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaDto.php
git commit -m "refactor(categorias): adicionar @var array shape + @throws nos DTOs (#16)"
```

---

## Ordem de execução

T1 → T2 → T3 → T4

(Podem ser feitas em paralelo T1+T2, mas T3 e T4 dependem de ambas estarem completas.)

---

## Estimativa

Muito baixa complexidade — 4 linhas de PHPDoc em 2 ficheiros. Risco zero de regressão.
