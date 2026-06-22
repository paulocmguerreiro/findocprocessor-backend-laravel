# Plano — Issue #36: Autorização por roles/permissions

**Issue:** #36
**Branch:** `feat/autorizacao-roles-permissions`
**Spec:** `docs/specs/2026-06-22-autorizacao-roles-permissions.md`

---

## T1 — Instalar Spatie Laravel Permission

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

Verificar: tabelas `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` criadas.

**Ficheiros afectados:**
- `composer.json` + `composer.lock`
- `config/permission.php` (publicado)
- `database/migrations/*_create_permission_tables.php` (publicado)

---

## T2 — Actualizar model `User`

Ficheiro: `app/Models/User.php`

1. Adicionar `use Spatie\Permission\Traits\HasRoles;`
2. Adicionar `HasRoles` à lista de traits
3. Adicionar `@property-read` para `$roles` e `$permissions`

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * ...existentes...
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, Permission> $permissions
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;
    // ...
}
```

Correr `composer test:types` para verificar Larastan.

---

## T3 — Criar `RolesPermissionsSeeder`

Ficheiro: `database/seeders/RolesPermissionsSeeder.php`

```php
final class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Permissions
        $todasPermissions = [
            'entidades.ver', 'entidades.criar', 'entidades.actualizar', 'entidades.eliminar',
            'categorias-documento.ver', 'categorias-documento.criar',
            'categorias-documento.actualizar', 'categorias-documento.eliminar',
        ];
        foreach ($todasPermissions as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'sanctum']);
        }

        // 2. Roles + atribuição de permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $admin->syncPermissions($todasPermissions);

        $utilizador = Role::firstOrCreate(['name' => 'utilizador', 'guard_name' => 'sanctum']);
        $utilizador->syncPermissions(['entidades.ver', 'categorias-documento.ver']);

        // 3. Utilizador admin de desenvolvimento
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@findocprocessor.test'],
            ['name' => 'Admin FinDocProcessor', 'password' => Hash::make('password')]
        );
        $adminUser->assignRole('admin');
        $adminUser->createToken('dev-token', ['api']);
    }
}
```

**Nota:** guard `sanctum` — as rotas usam `auth:sanctum`; as permissions e roles devem ser registadas no guard `sanctum`, não no `web`.

---

## T4 — Actualizar `DatabaseSeeder`

Ficheiro: `database/seeders/DatabaseSeeder.php`

Adicionar `$this->call(RolesPermissionsSeeder::class)` antes do `User::factory()->create(...)` existente.

---

## T5 — Implementar `EntidadePolicy`

Ficheiro: `app/Policies/EntidadePolicy.php`

Substituir `return true` por `$utilizador->hasPermissionTo(...)` em cada método conforme mapeamento da Spec (secção 4).

---

## T6 — Implementar `CategoriaDocumentoPolicy`

Ficheiro: `app/Policies/CategoriaDocumentoPolicy.php`

Mesma substituição com permissions `categorias-documento.*`.

---

## T7 — Testes Feature de Policy

### `tests/Feature/Features/Entidade/EntidadePolicyTest.php`

Cenários (ver Spec secção 5):
- `utilizador` recebe 403 em: criar, actualizar, eliminar, converter em Empresa Mãe
- `admin` recebe 200/201 em todos os endpoints
- Ambos recebem 200 em listar e ver

### `tests/Feature/Features/CategoriaDocumento/CategoriaDocumentoPolicyTest.php`

Mesma estrutura — permissions `categorias-documento.*`.

**Padrão de cada ficheiro:**
```php
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});
```

---

## T8 — Lint + Refactor + Testes completos

```bash
composer lint
composer refactor
composer test
```

Zero erros obrigatórios.

---

## T9 — Actualizar System Spec

1. `docs/system_spec/03-models/user.md` — adicionar `HasRoles`, `$roles`, `$permissions`, nota sobre guard `sanctum`
2. `docs/system_spec/04-infra/autorizacao.md` — criar ficheiro com: Spatie, roles, permissions, matriz, seeder, cache nos testes
3. `docs/system_spec/00-index.md` — adicionar linha para `04-infra/autorizacao.md`

---

## Verificação final (CA mapping)

| CA | Verificação |
|---|---|
| CA-01 | `php artisan migrate` sem erros; tabelas Spatie existem |
| CA-02 | `$user->hasPermissionTo('entidades.ver')` retorna `bool` sem erro |
| CA-03 | `RolesPermissionsSeeder::run()` cria roles e permissions correctamente |
| CA-04 | Policies sem `return true`; usam `hasPermissionTo()` |
| CA-05 | Testes de integração passam com utilizador autenticado via Sanctum |
| CA-06 | 403 para `utilizador` em operações de escrita; 200 para `admin` |
| CA-07 | `admin@findocprocessor.test` existe após seeder com role `admin` e token |
| CA-17 | `composer test` — 100% coverage, 100% type coverage, zero Larastan |
