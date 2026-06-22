# Brief — Refactor: Helpers de Auth e describe() por Role nos Testes

**Issue:** #48
**Data:** 2026-06-22
**Slug:** `refactor-tests-auth-helpers-describe`
**Tipo:** refactor (sem mudança de comportamento)

---

## Problema

Todos os ficheiros de teste (Feature e Unit) repetem as mesmas 3–4 linhas de boilerplate de autenticação:

```php
// em cada ficheiro — global beforeEach
app(PermissionRegistrar::class)->forgetCachedPermissions();

// em cada describe() ou teste
$utilizador = User::factory()->create();
$utilizador->assignRole('admin');
Sanctum::actingAs($utilizador, ['api']); // ou $this->actingAs($utilizador)
```

Os Unit Action tests têm um anti-padrão adicional: o `beforeEach` global configura um admin, e depois alguns testes individuais sobrescrevem esse contexto ao criar um novo utilizador internamente. O admin criado pelo `beforeEach` fica desperdiçado nesses testes. Os Policy tests (já correctos) usam `describe('Role admin')` / `describe('Role utilizador')` — os Action tests devem seguir o mesmo modelo.

---

## Solução

Três mudanças ortogonais e independentes:

1. **`tests/Pest.php`** — global hook + helper functions
2. **Unit Action tests** — `describe()` por role em vez de override inline
3. **Feature tests** — usar helpers para reduzir boilerplate

---

## Descobertas técnicas (MCP search-docs)

- `pest()->extend(TestCase::class)->beforeEach(fn () => ...)->in('Feature', 'Unit')` é a forma idiomática de definir hooks globais por directório em Pest 4
- Alternativamente, `beforeEach()` no topo de `Pest.php` aplica-se a **todos** os ficheiros carregados — mais simples, igualmente correcto
- Helper functions em `tests/Pest.php` são automaticamente carregadas para todos os testes — padrão documentado e recomendado pela Pest
- `describe()` blocks podem ser aninhados; cada um tem o seu próprio `beforeEach` scoped

---

## Ficheiros afectados

**A modificar:**
- `tests/Pest.php` — global beforeEach + 4 funções helper
- `tests/Unit/Features/Entidade/` — 7 ficheiros de Action tests
- `tests/Unit/Features/CategoriaDocumento/` — 5 ficheiros de Action tests
- `tests/Feature/Features/Entidade/` — 6 ficheiros
- `tests/Feature/Features/CategoriaDocumento/` — 5 ficheiros

**A actualizar:**
- `docs/system_spec/07-testing.md` — documentar os novos helpers e o padrão describe() por role

**Não afectados:**
- Policy tests (já correctos), ArchTest, DTOs, Resources, Model tests

---

## Riscos identificados

- Nenhum risco de regressão: é refactoring puro; o `composer test` valida zero mudanças de comportamento
- Risco menor: imports não removidos → Larastan/Pint assinalaria; remover é parte dos CAs

---

## Questões em aberto

Nenhuma — escopo completamente definido.
