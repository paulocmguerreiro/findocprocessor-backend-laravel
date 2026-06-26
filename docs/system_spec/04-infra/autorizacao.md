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

| Permission | Descrição | Migration |
|---|---|---|
| `entidades.ver` | Listar e ver entidades | `seed_roles_and_permissions` |
| `entidades.criar` | Criar entidade | `seed_roles_and_permissions` |
| `entidades.actualizar` | Actualizar entidade | `seed_roles_and_permissions` |
| `entidades.eliminar` | Eliminar entidade | `seed_roles_and_permissions` |
| `categorias-documento.ver` | Listar e ver categorias de documento | `seed_roles_and_permissions` |
| `categorias-documento.criar` | Criar categoria de documento | `seed_roles_and_permissions` |
| `categorias-documento.actualizar` | Actualizar categoria de documento | `seed_roles_and_permissions` |
| `categorias-documento.eliminar` | Eliminar categoria de documento | `seed_roles_and_permissions` |
| `roles.ver` | Listar e ver roles | `seed_roles_permissions_v2` |
| `roles.criar` | Criar role | `seed_roles_permissions_v2` |
| `roles.actualizar` | Actualizar role | `seed_roles_permissions_v2` |
| `roles.eliminar` | Eliminar role | `seed_roles_permissions_v2` |
| `utilizadores.atribuir-role` | Atribuir role a utilizador | `seed_roles_permissions_v2` |
| `documentos.ver` | Listar, ver e descarregar documentos | `seed_documentos_permissions` |
| `documentos.criar` | Registar manualmente e receber upload de documento | `seed_documentos_permissions` |
| `documentos.actualizar` | Corrigir, reprocessar e transições de pipeline | `seed_documentos_permissions` |
| `documentos.eliminar` | Eliminar documento | `seed_documentos_permissions` |

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
| `roles.ver` | ✅ | ❌ |
| `roles.criar` | ✅ | ❌ |
| `roles.actualizar` | ✅ | ❌ |
| `roles.eliminar` | ✅ | ❌ |
| `utilizadores.atribuir-role` | ✅ | ❌ |
| `documentos.ver` | ✅ | ✅ |
| `documentos.criar` | ✅ | ❌ |
| `documentos.actualizar` | ✅ | ❌ |
| `documentos.eliminar` | ✅ | ❌ |

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

## Checklist por feature com autorização

Toda a feature cujo recurso tem operações protegidas tem de ligar a autorização **por completo** — não basta criar a Policy. Sem as três peças, o recurso fica de acesso aberto e os testes passam por engano (Policy permissiva mascara a lacuna). Aplicar à imagem de `Entidade`/`CategoriaDocumento`:

1. **Migration de permissões** — `seed_<recurso>_permissions` cria `<recurso>.{ver,criar,actualizar,eliminar}`; `admin` recebe todas, `utilizador` só `<recurso>.ver`. Migration nova (nunca editar existente), com `forgetCachedPermissions()`.
2. **Policy real** — `hasPermissionTo('<recurso>.<accao>')` por ability: `viewAny`/`view` → `.ver`, `create` → `.criar`, `update` → `.actualizar`, `delete` → `.eliminar`. **Nunca `return true`** (stub é dívida que mascara a falta de autorização).
3. **`tests/Unit/Policies/<X>PolicyTest.php`** — valida `admin` (todas as abilities permitidas) vs `utilizador` (só leitura permitida, escritas negadas). Não testar o stub.

Além disto, os testes de Action e de feature da slice cobrem a **matriz de 4 actores** (`07-testing.md`).

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

| Policy | Modelo | Ficheiro | Registo |
|---|---|---|---|
| `EntidadePolicy` | `Entidade` | `app/Policies/EntidadePolicy.php` | Automático (convenção nome `ModelPolicy`) |
| `CategoriaDocumentoPolicy` | `CategoriaDocumento` | `app/Policies/CategoriaDocumentoPolicy.php` | Automático (convenção nome `ModelPolicy`) |
| `DocumentoPolicy` | `Documento` | `app/Policies/DocumentoPolicy.php` | Automático (convenção nome `ModelPolicy`) |
| `RolePolicy` | `Spatie\Permission\Models\Role` | `app/Policies/RolePolicy.php` | `Gate::policy(Role::class, RolePolicy::class)` em `AppServiceProvider` |
| `UtilizadorPolicy` | `User` | `app/Policies/UtilizadorPolicy.php` | `#[UsePolicy(UtilizadorPolicy::class)]` no modelo `User` |

> **Nota:** Modelos de terceiros (como `Spatie\Permission\Models\Role`) não suportam `#[UsePolicy]` — têm de ser registados via `Gate::policy()` no `AppServiceProvider`.

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
