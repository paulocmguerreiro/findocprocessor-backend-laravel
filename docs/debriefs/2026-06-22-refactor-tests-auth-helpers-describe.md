# Debrief — Refactor: Helpers de Auth e describe() por Role nos Testes

**Issue:** #48
**Data:** 2026-06-22
**Branch:** `refactor/tests-auth-helpers-describe`
**Tipo:** refactor (sem mudança de comportamento)

---

## O que foi feito

Refactoring puro dos 22 ficheiros de testes (11 Unit + 11 Feature) para eliminar boilerplate de autenticação repetido e estruturar os Unit tests com `describe()` por role, alinhando-os com o padrão já existente nos Policy tests.

### Alterações por tarefa

**T1 — `tests/Pest.php`**
- `beforeEach` global com `forgetCachedPermissions()` — antes repetido em cada ficheiro
- 4 funções helper: `criarAdmin()`, `criarUtilizador()`, `criarEAutenticarAdmin()`, `criarEAutenticarUtilizador()`

**T2/T3 — Unit tests (11 ficheiros)**
- Removido `beforeEach` global com criação de admin inline
- Removidos imports `PermissionRegistrar` e `User` (onde dispensável)
- Happy path movido para `describe('como admin')` com `beforeEach(fn () => $this->actingAs(criarAdmin()))`
- Teste de autorização movido para `describe('sem permissão de escrita/leitura')` — elimina o override inline que desperdiçava o utilizador do `beforeEach` global

**T4/T5 — Feature tests (11 ficheiros)**
- Removido `beforeEach` global com `forgetCachedPermissions()` (agora global em Pest.php)
- `describe('autenticado') { beforeEach }` simplificado para `criarEAutenticarAdmin()`
- Testes 403 simplificados para `criarEAutenticarUtilizador()` (1 linha em vez de 3)
- Imports `User`, `Sanctum`, `PermissionRegistrar` removidos

**T6 — Qualidade**
- Rector aplicou `AddArrowFunctionReturnTypeRector` às arrow functions `beforeEach` nos Feature tests (`fn (): User =>`)
- Pint resolveu os FQCNs introduzidos pelo Rector (`\App\Models\User` → import)

**T7 — `docs/system_spec/07-testing.md`**
- Nova secção "Helpers globais de autenticação" com tabela dos 4 helpers, padrões `describe()` para Unit e Feature tests, e nota sobre o `beforeEach` global

---

## Decisões tomadas

**`describe('sem permissão de leitura')` sem `beforeEach` em Ver/Listar**
Nos ficheiros de leitura (`Ver*`, `Listar*`), o describe de "sem permissão" usa `User::factory()->create()` inline no teste (sem role), em vez de `criarUtilizador()`. Mantém o intent explícito: não é um utilizador com role errada — é um utilizador sem qualquer role, que não tem a permissão `*.ver`.

**`payloadActualizar()` em `ActualizarCategoriaTest.php` mantida local**
O plano especificava explicitamente "manter" esta função local ao ficheiro. Não foi movida para Pest.php por ser específica de um endpoint.

**Rector adicionou return types às arrow functions**
`fn () => criarEAutenticarAdmin()` → `fn (): User => criarEAutenticarAdmin()`. Mudança correcta: Rector tem razão, é mais explícito. Pint converteu os FQCNs para imports na segunda passagem.

---

## Resultado

- 229 testes, 100% cobertura, PHPStan nível 9 sem erros
- 7 commits na branch
- Redução líquida: ~345 linhas removidas, ~160 adicionadas (net -185 linhas de boilerplate)

---

## Aprendizagens

**Vertical Slice / Actions**
O padrão `describe()` por role nos Unit tests reflecte directamente a estrutura de autorização das Actions: cada Action tem um Gate, e os testes agrupam os cenários pelo contexto de autorização em que são invocados. É mais fácil raciocinar sobre "o que este describe testa" do que sobre testes avulsos com overrides inline. O alinhamento com os Policy tests (que já usavam `describe()` por role) torna o test suite conceptualmente uniforme.

**PHP 8.5 / Pest 4**
O helper functions pattern em `Pest.php` é idiomático em Pest 4: funções definidas no ficheiro de configuração são automaticamente disponíveis em todos os testes do suite, sem necessidade de `use function` ou `import`. É uma forma limpa de DRY em testes sem criar abstracções desnecessárias. A interacção Rector→Pint (Rector adiciona FQCNs, Pint converte para imports) é o comportamento esperado quando as duas ferramentas correm em sequência — `composer lint` a seguir a `composer refactor` resolve sempre.
