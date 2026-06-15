# Brief — Issue #17: Auditoria de tipagem (@var + @throws)

**Data:** 2026-06-15
**Branch:** `chore/auditoria-tipagem-var-throws`
**Issue:** [#17](https://github.com/paulocmguerreiro/findocprocessor-backend-laravel/issues/17)
**Tipo:** chore / refactor de anotações
**SYSTEM_SPEC a actualizar:** nenhum (sem mudança de contrato)

---

## Contexto

O CLAUDE.md formalizou duas regras de tipagem (A e B). Esta issue garante que todo o código em `app/` está em conformidade antes que novas features as assumam como baseline.

- **Regra A:** `$request->validated()` deve ter `@var` array shape antes de desestruturar.
- **Regra B:** qualquer método que possa lançar excepções (via `throw` explícito ou `findOrFail()`) deve declarar `@throws`.

---

## Resultado da varredura

| Regra | Ficheiro | Estado |
|---|---|---|
| A (`@var` em `validated()`) | `CriarCategoriaDto::fromRequest()` | ✓ conforme |
| A (`@var` em `validated()`) | `ActualizarCategoriaDto::fromRequest()` | ✓ conforme |
| B (`throw` explícito) | `CriarCategoriaDto::fromRequest()` | ✓ conforme |
| B (`throw` explícito) | `ActualizarCategoriaDto::fromRequest()` | ✓ conforme |
| B (`findOrFail()`) | `ActualizarCategoriaAction::handle()` | ✓ conforme |
| B (`findOrFail()`) | `EliminarCategoriaAction::handle()` | ✗ ausente |
| B (`findOrFail()`) | `VerCategoriaAction::handle()` | ✗ ausente |

**Trabalho restante:** adicionar `@throws ModelNotFoundException` a `EliminarCategoriaAction::handle()` e `VerCategoriaAction::handle()`.

---

## Decisões de desenho

### Repositório
Dispensado — CRUD simples, ≤ 1 query por `handle()`, sem lógica partilhada.

### Escopo da Regra B
A regra diz "métodos que contenham `throw`". Optamos por incluir métodos que chamam `findOrFail()`, por:
1. `findOrFail()` lança `ModelNotFoundException` — excepção real que os callers precisam conhecer.
2. `ActualizarCategoriaAction::handle()` já documenta `@throws ModelNotFoundException` — consistência interna.
3. `ModelNotFoundException` é convertida pelo Laravel para `NotFoundHttpException` no handler global, mas isso não isenta a action de declarar o contrato.

---

## Riscos identificados

- Nenhum — adição pura de PHPDoc; comportamento runtime inalterado.
- Larastan nível 9 já verde (0 erros) antes das alterações.

## Questões em aberto

- Nenhuma.

---

## Fora de âmbito

- Não alterar lógica de negócio.
- Não alterar testes.
- Não adicionar anotações além de `@throws ModelNotFoundException`.
