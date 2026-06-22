# System Spec — Autorização (Roles e Permissions)

> Spatie Laravel Permission `^8.0` — roles e permissions granulares por utilizador.

---

## Pacote

`spatie/laravel-permission ^8.0` — instalado via `composer require`.

Configuração publicada em `config/permission.php`. Guard: `web` (único guard em `config/auth.php`; não usar `sanctum` como guard_name — Sanctum autentica via middleware, não regista guard separado).

---

## Roles e Permissions

### Roles disponíveis

| Role | Descrição |
|---|---|
| `admin` | Acesso total — todas as permissions |
| `utilizador` | Acesso de leitura apenas |

### Permissions disponíveis

| Permission | Descrição |
|---|---|
| `entidades.ver` | Listar e ver entidades |
| `entidades.criar` | Criar entidade |
| `entidades.actualizar` | Actualizar entidade |
| `entidades.eliminar` | Eliminar entidade |
| `categorias-documento.ver` | Listar e ver categorias de documento |
| `categorias-documento.criar` | Criar categoria de documento |
| `categorias-documento.actualizar` | Actualizar categoria de documento |
| `categorias-documento.eliminar` | Eliminar categoria de documento |

### Matriz role → permissions

| Permission | `admin` | `utilizador` |
|---|---|---|
| `entidades.ver` | ✅ | ✅ |
| `entidades.criar` | ✅ | ❌ |
| `entidades.actualizar` | ✅ | ❌ |
| `entidades.eliminar` | ✅ | ❌ |
| `categorias-documento.ver` | ✅ | ✅ |
| `categorias-documento.criar` | ✅ | ❌ |
| `categorias-documento.actualizar` | ✅ | ❌ |
| `categorias-documento.eliminar` | ✅ | ❌ |

---

## Data Migration vs Seeder

### Data Migration (todos os ambientes)

`database/migrations/2026_06_22_150715_seed_roles_and_permissions.php`

Cria roles e permissions automaticamente com `php artisan migrate`. Corre em desenvolvimento, testes CI, staging e produção. É a fonte da verdade para a estrutura de autorização.

**Padrão:**
```php
public function up(): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($todasPermissions as $nome) { Permission::create(['name' => $nome]); }
    Role::create(['name' => 'admin'])->syncPermissions($todasPermissions);
    Role::create(['name' => 'utilizador'])->syncPermissions(['entidades.ver', 'categorias-documento.ver']);
}
```

### Seeder (desenvolvimento apenas)

`database/seeders/RolesPermissionsSeeder.php` — cria utilizador admin de desenvolvimento (`admin@findocprocessor.test`) com role `admin` e token Sanctum `dev-token`. Não correr em produção.

### Adicionar novas permissions (features futuras)

Criar nova data migration que chama `forgetCachedPermissions()` + `Permission::create()` + `$role->givePermissionTo()`. Nunca editar a migration existente.

---

## Policies

### Padrão

Policies usam `hasPermissionTo()` (granular) em vez de `hasRole()` (role-based), para que a lógica de autorização não dependa de nomes de roles.

```php
public function view(User $utilizador, Entidade $entidade): bool
{
    return $utilizador->hasPermissionTo('entidades.ver');
}
```

### Policies existentes

| Policy | Modelo | Ficheiro |
|---|---|---|
| `EntidadePolicy` | `Entidade` | `app/Policies/EntidadePolicy.php` |
| `CategoriaDocumentoPolicy` | `CategoriaDocumento` | `app/Policies/CategoriaDocumentoPolicy.php` |

Registo automático via `AppServiceProvider` — convenção Laravel (nome `ModelPolicy`).

---

## Autorização Dupla Camada

A autorização é aplicada em **dois pontos independentes**:

1. **`FormRequest::authorize()`** — contexto HTTP; via `Gate::authorize('view', $model)`
2. **Action::handle()** — início do método; via `Gate::authorize('view', $model)` — cobre Jobs, Artisan, testes directos à Action

Ambas as camadas lançam `AuthorizationException` quando a permission é negada.

> Padrão completo: `02-shared/padroes-acoes.md`

---

## Cache em Testes

Nos testes que usam permissions, limpar cache no `beforeEach`:

```php
beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    // ...
});
```

`RefreshDatabase` recria as tabelas e corre as migrations (incluindo a data migration de roles/permissions), garantindo que roles existem sem seeder manual.

---

## Trait no Model User

`HasRoles` adicionado a `app/Models/User.php`. Expõe:
- `$user->assignRole('admin')`
- `$user->hasPermissionTo('entidades.ver')`
- `@property-read Collection<int, Role> $roles`
- `@property-read Collection<int, Permission> $permissions`

> Detalhe do Model: `03-models/user.md`
