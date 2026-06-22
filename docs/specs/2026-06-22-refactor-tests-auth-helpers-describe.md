# Spec — Refactor: Helpers de Auth e describe() por Role nos Testes

**Issue:** #48
**Data:** 2026-06-22
**Slug:** `refactor-tests-auth-helpers-describe`

---

## Inventário dos ficheiros e padrões actuais

### Unit Action tests — anti-padrão a eliminar

| Ficheiro | beforeEach global | Teste "sem permissão" (override inline) | Tipo de override |
|---|---|---|---|
| `CriarEntidadeActionTest.php` | admin | cria `utilizador` + `actingAs` dentro do teste | `assignRole('utilizador')` |
| `ActualizarEntidadeActionTest.php` | admin | cria `utilizador` + `actingAs` dentro do teste | `assignRole('utilizador')` |
| `EliminarEntidadeActionTest.php` | admin | cria `utilizador` + `actingAs` dentro do teste | `assignRole('utilizador')` |
| `ConverterEmEmpresaMaeActionTest.php` | admin | cria `utilizador` + `actingAs` dentro do teste | `assignRole('utilizador')` |
| `VerEntidadeActionTest.php` | admin | cria user sem role + `actingAs` dentro do teste | sem `assignRole` |
| `ListarEntidadesActionTest.php` | admin | cria user sem role + `actingAs` dentro do teste | sem `assignRole` |
| `CriarCategoriaActionTest.php` | admin | cria `utilizador` + `actingAs` dentro do teste | `assignRole('utilizador')` |
| `ActualizarCategoriaActionTest.php` | admin | cria `utilizador` + `actingAs` dentro do teste | `assignRole('utilizador')` |
| `EliminarCategoriaActionTest.php` | admin | cria `utilizador` + `actingAs` dentro do teste | `assignRole('utilizador')` |
| `VerCategoriaActionTest.php` | admin | cria user sem role + `actingAs` dentro do teste | sem `assignRole` |
| `ListarCategoriasActionTest.php` | admin | cria user sem role + `actingAs` dentro do teste | sem `assignRole` |
| `RemoverMarcacaoEmpresaMaeActionTest.php` | **nenhum** | **nenhum** (action interna, sem Gate) | — |

> `RemoverMarcacaoEmpresaMaeActionTest.php` **não é afectado** — action interna sem autorização.

### Feature tests — boilerplate a simplificar

Todos os 11 ficheiros Feature seguem o mesmo padrão:
- `describe('autenticado') { beforeEach com 3 linhas de criação de admin + Sanctum }`
- 1–2 testes fora do describe com criação inline de 'utilizador' ou user sem role

---

## Contratos dos helpers (Pest.php)

```php
// Para Unit tests (sem Sanctum)
function criarAdmin(): User     // cria user com role 'admin', devolve instância
function criarUtilizador(): User // cria user com role 'utilizador', devolve instância

// Para Feature tests (com Sanctum)
function criarEAutenticarAdmin(): User      // criarAdmin() + Sanctum::actingAs($u, ['api'])
function criarEAutenticarUtilizador(): User  // criarUtilizador() + Sanctum::actingAs($u, ['api'])
```

### Uso nos Unit tests

```php
describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));
    // testes happy path
});

describe('sem permissão de escrita', function (): void {  // ou 'leitura'
    beforeEach(fn () => $this->actingAs(criarUtilizador()));  // ou User::factory()->create() para sem role
    it('lança AuthorizationException...', ...);
});
```

### Uso nos Feature tests

```php
describe('autenticado', function (): void {
    beforeEach(fn () => criarEAutenticarAdmin());
    // testes happy path
});

it('utilizador sem permissão recebe 403', function (): void {
    criarEAutenticarUtilizador();
    $this->postJson(...)->assertForbidden();
});
```

---

## Hook global em Pest.php

```php
// Mantém o uses() existente e adiciona beforeEach global
uses(TestCase::class)->in('Feature', 'Unit');

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});
```

> O `beforeEach` global corre antes de todos os testes no suite. Ficheiros sem permissões (DTOs, Resources) não são afectados — a chamada é idempotente e barata.

---

## Variação "sem role" (Ver e Listar actions)

Para VerEntidade, ListarEntidades, VerCategoria, ListarCategorias — o utilizador sem permissão não tem qualquer role (não `assignRole('utilizador')`). Nestes `describe`:

```php
describe('sem permissão de leitura', function (): void {
    beforeEach(fn () => $this->actingAs(User::factory()->create()));
    it('lança AuthorizationException...', ...);
});
```

> Mantém-se a importação `use App\Models\User` nestes ficheiros.

---

## Imports a remover após refactoring

| Import | Removido de |
|---|---|
| `use Spatie\Permission\PermissionRegistrar;` | todos os ficheiros com `forgetCachedPermissions()` no beforeEach local |
| `use Laravel\Sanctum\Sanctum;` | Feature tests onde `criarEAutenticarAdmin/Utilizador` substitui o uso directo |
| `use App\Models\User;` | ficheiros onde `User::factory()` deixa de aparecer directamente |

> Excepção: ficheiros com variação "sem role" mantêm `use App\Models\User`.

---

## System spec a actualizar

`docs/system_spec/07-testing.md` — adicionar secção **Helpers globais de autenticação** com:
- Os 4 helpers disponíveis e quando usar cada um
- O padrão `describe('como admin')` + `describe('sem permissão')` para Unit tests
- Referência ao `beforeEach` global em `Pest.php`

---

## Ficheiros fora de âmbito

- `tests/Unit/Policies/` — já usam `describe()` por role correctamente
- `tests/Unit/Models/` — sem autenticação relevante
- `tests/ArchTest.php`, DTOs, Resources, Requests — sem auth
- `tests/Feature/Features/Auth/` — testes de auth própria, sem o padrão repetido
- `tests/Feature/Shared/` — sem auth de utilizadores
