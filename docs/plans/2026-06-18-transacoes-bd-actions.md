# Plano — Issue #34: Transações de BD nas Actions de escrita

**Data:** 2026-06-18
**Issue:** #34
**Slug:** `transacoes-bd-actions`
**Branch:** `chore/transacoes-bd-actions`
**Spec:** `docs/specs/2026-06-18-transacoes-bd-actions.md`

---

## Tarefas

### T1 — `CriarCategoriaAction`: envolver create() em DB::transaction()

**Ficheiro:** `app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php`

- Adicionar `use Illuminate\Support\Facades\DB`
- Envolver `CategoriaDocumento::create([...])` em `DB::transaction(fn() => ...)`
- Adicionar `@throws \Throwable` ao PHPDoc

---

### T2 — `ActualizarCategoriaAction`: envolver fill/save/refresh em DB::transaction()

**Ficheiro:** `app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php`

- Adicionar `use Illuminate\Support\Facades\DB`
- Envolver `fill()->save()` + `refresh()` em `DB::transaction(function () use (...): CategoriaDocumento { ... })`
- Adicionar `@throws \Throwable` ao PHPDoc

---

### T3 — `EliminarCategoriaAction`: envolver delete() em DB::transaction()

**Ficheiro:** `app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php`

- Adicionar `use Illuminate\Support\Facades\DB`
- Envolver `$categoria->delete()` em `DB::transaction(fn() => ...)`
- Adicionar `@throws \Throwable` ao PHPDoc

---

### T4 — Testes: CriarCategoriaActionTest (novo)

**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/CriarCategoriaActionTest.php`

- `cria categoria com dados válidos` — happy path
- `faz rollback quando ocorre excepção após insert` — `CategoriaDocumento::created()` hook lança `\RuntimeException`; `assertDatabaseCount('categorias_documento', 0)`

---

### T5 — Testes: adicionar rollback a ActualizarCategoriaActionTest

**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaActionTest.php`

- `faz rollback quando ocorre excepção durante update` — `CategoriaDocumento::saved()` hook lança; campos originais preservados

---

### T6 — Testes: adicionar rollback a EliminarCategoriaActionTest

**Ficheiro:** `tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php`

- `faz rollback quando ocorre excepção durante eliminação` — `CategoriaDocumento::deleting()` hook lança; `assertDatabaseHas` verifica que o registo permanece

---

### T7 — CLAUDE.md: documentar padrão obrigatório

**Ficheiro:** `CLAUDE.md`

- Adicionar à secção "Padrões obrigatórios": `DB::transaction()` obrigatório + Gate fora + nota sobre Jobs/`after_commit`

---

### T8 — system_spec/04-infra.md: documentar padrão

**Ficheiro:** `docs/system_spec/04-infra.md`

- Adicionar secção "Transações de BD" com padrão canónico e lista das Actions que o implementam

---

### T9 — Qualidade: composer lint + composer test

- `composer lint` — Pint
- `composer test` — pipeline completa (lint, refactor dry-run, types, coverage)
- Corrigir eventuais erros antes de commitar

---

## Ordem de execução

```
T1 → T2 → T3   (Actions — independentes entre si, executar sequencialmente)
T4 → T5 → T6   (Testes — após as Actions)
T7 → T8        (Documentação — pode ser feita em paralelo com os testes)
T9             (Qualidade — sempre por último)
```

## Commit final

```
git add app/Features/CategoriaDocumento/Criar/CriarCategoriaAction.php
git add app/Features/CategoriaDocumento/Actualizar/ActualizarCategoriaAction.php
git add app/Features/CategoriaDocumento/Eliminar/EliminarCategoriaAction.php
git add tests/Unit/Features/CategoriaDocumento/CriarCategoriaActionTest.php
git add tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaActionTest.php
git add tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php
git add CLAUDE.md
git add docs/system_spec/04-infra.md
git commit -m "chore(infra): DB::transaction() nas Actions de escrita — Issue #34"
```
