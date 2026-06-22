# Spec — Issue #36: Autorização por roles/permissions

**Issue:** #36
**Slug:** `autorizacao-roles-permissions`
**Branch:** `feat/autorizacao-roles-permissions`

---

## 1. Dependência Spatie

### `composer.json`
```
"spatie/laravel-permission": "^6.0"
```

Instalação: `composer require spatie/laravel-permission`
Publicação: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
Migração: `php artisan migrate`

---

## 2. Model `User`

Ficheiro: `app/Models/User.php`

**Adicionar trait `HasRoles`** de `Spatie\Permission\Traits\HasRoles`.

**`@property-read` a adicionar:**
```php
@property-read Collection<int, \Spatie\Permission\Models\Role> $roles
@property-read Collection<int, \Spatie\Permission\Models\Permission> $permissions
```

O trait `HasRoles` expõe também `hasRole()`, `hasPermissionTo()`, `givePermissionTo()`, `assignRole()` — métodos; não precisam de `@property-read`.

---

## 3. Seeder

### `RolesPermissionsSeeder` — `database/seeders/RolesPermissionsSeeder.php`

```
Permissions criadas (ordem de criação):
  entidades.ver
  entidades.criar
  entidades.actualizar
  entidades.eliminar
  categorias-documento.ver
  categorias-documento.criar
  categorias-documento.actualizar
  categorias-documento.eliminar

Roles e permissions atribuídas:
  admin       → todas as 8 permissions
  utilizador  → entidades.ver, categorias-documento.ver

Utilizador admin de desenvolvimento:
  name:  Admin FinDocProcessor
  email: admin@findocprocessor.test
  password: password (hashed)
  role: admin
  token Sanctum: nome 'dev-token', abilities ['api']
```

Classe: `final class RolesPermissionsSeeder extends Seeder`

### `DatabaseSeeder` — actualização

```php
public function run(): void
{
    $this->call(RolesPermissionsSeeder::class);

    // utilizador de teste sem role (utilizado nos testes existentes)
    User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
}
```

---

## 4. Policies

### `EntidadePolicy` — `app/Policies/EntidadePolicy.php`

```php
final class EntidadePolicy
{
    public function viewAny(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('entidades.ver');
    }

    public function view(User $utilizador, Entidade $entidade): bool
    {
        return $utilizador->hasPermissionTo('entidades.ver');
    }

    public function create(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('entidades.criar');
    }

    public function update(User $utilizador, Entidade $entidade): bool
    {
        return $utilizador->hasPermissionTo('entidades.actualizar');
    }

    public function delete(User $utilizador, Entidade $entidade): bool
    {
        return $utilizador->hasPermissionTo('entidades.eliminar');
    }
}
```

### `CategoriaDocumentoPolicy` — `app/Policies/CategoriaDocumentoPolicy.php`

Mesma estrutura — permissions `categorias-documento.*`.

---

## 5. Testes

### Localização e estrutura

```
tests/Feature/Features/Entidade/
  EntidadePolicyTest.php        ← novo

tests/Feature/Features/CategoriaDocumento/
  CategoriaDocumentoPolicyTest.php   ← novo
```

Não há testes unitários de Policy (a Policy só faz `hasPermissionTo()` — lógica trivial; o valor está no teste de integração HTTP).

### Padrão dos testes

Cada ficheiro de teste:
1. `use RefreshDatabase` — recria BD entre testes (elimina cache de permissions implicitamente)
2. Helper `criarUtilizadorComRole(string $role): User` — cria utilizador, atribui role, devolve instância com token
3. Cenários por endpoint:

| Cenário | Role | Esperado |
|---|---|---|
| Listar (GET /api/entidades) | `utilizador` | 200 |
| Listar (GET /api/entidades) | `admin` | 200 |
| Criar (POST /api/entidades) | `utilizador` | 403 |
| Criar (POST /api/entidades) | `admin` | 201 |
| Ver (GET /api/entidades/{id}) | `utilizador` | 200 |
| Actualizar (PUT /api/entidades/{id}) | `utilizador` | 403 |
| Actualizar (PUT /api/entidades/{id}) | `admin` | 200 |
| Eliminar (DELETE /api/entidades/{id}) | `utilizador` | 403 |
| Eliminar (DELETE /api/entidades/{id}) | `admin` | 200 |
| Converter em Empresa Mãe (PATCH) | `utilizador` | 403 |
| Converter em Empresa Mãe (PATCH) | `admin` | 200 |

Mesma estrutura para `CategoriaDocumentoPolicyTest`.

### Cache do Spatie nos testes

```php
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});
```

Adicionado em cada ficheiro de teste de Policy — garante que a cache não persiste entre testes no mesmo processo.

### Seeder nos testes

Os testes de Policy criam roles e permissions via `RolesPermissionsSeeder` dentro do `setUp` ou via `$this->seed(RolesPermissionsSeeder::class)` — não dependem de fixtures manuais.

---

## 6. Impacto no ArchTest

Nenhum. `HasRoles` é trait no `User` que está em `App\Models`, fora do `App\Features`. O preset Laravel e as regras existentes não são afectados.

---

## 7. System Spec a actualizar

| Ficheiro | Operação |
|---|---|
| `docs/system_spec/03-models/user.md` | Adicionar `HasRoles`, relações `roles`/`permissions`, nota sobre Spatie |
| `docs/system_spec/04-infra/autorizacao.md` | Criar — Spatie, roles, permissions, seeder, cache |
| `docs/system_spec/00-index.md` | Adicionar `04-infra/autorizacao.md` na tabela de Infra |

---

## Critérios de aceitação (mapeamento)

| CA | Componente | Verificação |
|---|---|---|
| CA-01 | Migrations Spatie | `php artisan migrate` sem erros; tabelas `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` existem |
| CA-02 | `User` + `HasRoles` | ArchTest `strict_types` passa; `$user->hasPermissionTo(...)` acessível |
| CA-03 | `RolesPermissionsSeeder` | Roles `admin` e `utilizador` criados; permissions atribuídas correctamente |
| CA-04 | Policies | Métodos usam `hasPermissionTo()` — sem `return true` |
| CA-05 | `Gate::authorize()` duplo | FormRequests e Actions inalterados — o Gate resolve via Policy automaticamente |
| CA-06 | Testes Feature | 403 para `utilizador` em create/update/delete; 200 para `admin` em tudo |
| CA-07 | Seeder dev | `admin@findocprocessor.test` com role `admin` e token Sanctum criado pelo seeder |
