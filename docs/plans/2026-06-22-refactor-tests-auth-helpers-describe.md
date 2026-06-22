# Plano — Refactor: Helpers de Auth e describe() por Role nos Testes

**Issue:** #48
**Data:** 2026-06-22
**Branch:** `refactor/tests-auth-helpers-describe`

---

## Tarefas

### T1 — Actualizar `tests/Pest.php`

Adicionar `beforeEach` global + 4 funções helper:

```php
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function criarAdmin(): User
{
    $utilizador = User::factory()->create();
    $utilizador->assignRole('admin');
    return $utilizador;
}

function criarUtilizador(): User
{
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    return $utilizador;
}

function criarEAutenticarAdmin(): User
{
    $utilizador = criarAdmin();
    Sanctum::actingAs($utilizador, ['api']);
    return $utilizador;
}

function criarEAutenticarUtilizador(): User
{
    $utilizador = criarUtilizador();
    Sanctum::actingAs($utilizador, ['api']);
    return $utilizador;
}
```

**Verificar:** `composer lint && composer test:types`

---

### T2 — Restructurar Unit Action tests — Entidade (5 ficheiros)

Padrão a aplicar em cada ficheiro:

```php
// REMOVER: beforeEach global com forgetCachedPermissions + admin setup
// REMOVER: use Spatie\Permission\PermissionRegistrar (+ Sanctum + User se já não usado)

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));
    // mover todos os testes happy path aqui
});

describe('sem permissão de escrita', function (): void {   // ou 'leitura' para Ver/Listar
    beforeEach(fn () => $this->actingAs(criarUtilizador())); // ou User::factory()->create() para sem role
    // mover o teste 'lança AuthorizationException' aqui (sem a criação de user inline)
});
```

**Ficheiros:**
- `tests/Unit/Features/Entidade/CriarEntidadeActionTest.php`
- `tests/Unit/Features/Entidade/ActualizarEntidadeActionTest.php`
- `tests/Unit/Features/Entidade/EliminarEntidadeActionTest.php`
- `tests/Unit/Features/Entidade/ConverterEmEmpresaMaeActionTest.php`
- `tests/Unit/Features/Entidade/VerEntidadeActionTest.php` ← usa `User::factory()->create()` (sem role)
- `tests/Unit/Features/Entidade/ListarEntidadesActionTest.php` ← usa `User::factory()->create()` (sem role)

**Verificar:** `composer test` após cada ficheiro (ou em bloco no fim)

---

### T3 — Restructurar Unit Action tests — CategoriaDocumento (4 ficheiros)

Mesmo padrão do T2:

**Ficheiros:**
- `tests/Unit/Features/CategoriaDocumento/CriarCategoriaActionTest.php`
- `tests/Unit/Features/CategoriaDocumento/ActualizarCategoriaActionTest.php`
- `tests/Unit/Features/CategoriaDocumento/EliminarCategoriaActionTest.php`
- `tests/Unit/Features/CategoriaDocumento/VerCategoriaActionTest.php` ← usa `User::factory()->create()` (sem role)
- `tests/Unit/Features/CategoriaDocumento/ListarCategoriasActionTest.php` ← usa `User::factory()->create()` (sem role)

---

### T4 — Simplificar Feature tests — Entidade (6 ficheiros)

Padrão a aplicar:

```php
// REMOVER: beforeEach global com forgetCachedPermissions
// REMOVER: imports Sanctum, User, PermissionRegistrar (se dispensáveis)

describe('autenticado', function (): void {
    beforeEach(fn () => criarEAutenticarAdmin());
    // testes happy path — sem alteração
});

// testes 403/401 fora do describe — apenas substituir criação inline:
it('utilizador sem permissão recebe 403', function (): void {
    criarEAutenticarUtilizador();    // substituição das 3 linhas
    ...
});
```

**Ficheiros:**
- `tests/Feature/Features/Entidade/CriarEntidadeTest.php`
- `tests/Feature/Features/Entidade/ListarEntidadesTest.php` ← 200 com utilizador → `criarEAutenticarUtilizador()`
- `tests/Feature/Features/Entidade/VerEntidadeTest.php`
- `tests/Feature/Features/Entidade/ActualizarEntidadeTest.php`
- `tests/Feature/Features/Entidade/EliminarEntidadeTest.php`
- `tests/Feature/Features/Entidade/ConverterEmEmpresaMaeTest.php`

---

### T5 — Simplificar Feature tests — CategoriaDocumento (5 ficheiros)

Mesmo padrão do T4:

**Ficheiros:**
- `tests/Feature/Features/CategoriaDocumento/CriarCategoriaTest.php`
- `tests/Feature/Features/CategoriaDocumento/ListarCategoriasTest.php`
- `tests/Feature/Features/CategoriaDocumento/VerCategoriaTest.php`
- `tests/Feature/Features/CategoriaDocumento/ActualizarCategoriaTest.php`
- `tests/Feature/Features/CategoriaDocumento/EliminarCategoriaTest.php`

---

### T6 — Verificação final

```bash
composer lint      # Pint — formatar
composer refactor  # Rector — modernizar
composer test      # pipeline completa — zero regressões esperadas
```

---

### T7 — Actualizar `docs/system_spec/07-testing.md`

Adicionar secção **Helpers globais de autenticação** com:
- Os 4 helpers disponíveis e quando usar cada um
- Padrão `describe('como admin')` + `describe('sem permissão')` para Unit tests
- Referência ao `beforeEach` global em `Pest.php`

---

## Notas de implementação

- `RemoverMarcacaoEmpresaMaeActionTest.php` — **não tocar** (action interna, sem Gate)
- Policy tests, ArchTest, DTOs, Resources, Requests — **não tocar**
- Nos ficheiros "sem role" (Ver/Listar): manter `use App\Models\User`
- `payloadActualizar()` em `ActualizarCategoriaTest.php` é uma função local ao ficheiro — manter
- Ordem dos `describe()` nos Unit tests: `'como admin'` primeiro, `'sem permissão'` depois
