# Brief — Issue #50: Role + Utilizador — feature slice

**Issue:** #50 — `feat(laravel): Role + Utilizador — feature slice (Actions + Controller + FormRequests + Policies + Migration + Testes)`
**Data:** 2026-06-22
**Slug:** `role-utilizador-feature-slice`
**Depende de:** #36 (Spatie Permission — base implementada)

---

## Contexto

Com autenticação (Sanctum — #35) e autorização base (Spatie Permission — #36) implementadas, a aplicação tem roles e permissions definidos mas sem nenhum endpoint para os gerir. Um admin não consegue criar novos roles, atribuir permissões a um role, nem atribuir um role a outro utilizador — tudo isto tem de ser feito directamente na BD.

Esta issue implementa a camada de lógica completa para:
1. CRUD de roles via API (`GET/POST/PUT/DELETE /api/roles`)
2. Atribuição de role a utilizador (`PUT /api/utilizadores/{utilizador}/role`)

Inclui também a migration com as 5 novas permissions necessárias para controlar o acesso a estes endpoints.

---

## Âmbito

**Feature `Role` (`app/Features/Role/`):**
- `ListarRolesAction` — GET /api/roles
- `VerRoleAction` — GET /api/roles/{role}
- `CriarRoleAction` — POST /api/roles
- `ActualizarRoleAction` — PUT /api/roles/{role}
- `EliminarRoleAction` — DELETE /api/roles/{role}

**Feature `Utilizador` (`app/Features/Utilizador/`):**
- `AtribuirRoleAction` — PUT /api/utilizadores/{utilizador}/role

**Infra:**
- Nova migration com 5 permissions (`roles.ver`, `roles.criar`, `roles.actualizar`, `roles.eliminar`, `utilizadores.atribuir-role`)
- `RolePolicy` + `UtilizadorPolicy`
- `RoleResource` para serialização
- Actualização do `RolesPermissionsSeeder`
- Testes Unit + Feature por action

---

## Decisões técnicas

### Model do Spatie directamente (sem wrapper)

`Spatie\Permission\Models\Role` tem tudo o que é necessário: `name`, `guard_name`, `syncPermissions()`, `permissions` relation. Criar um Model `App\Models\Role` que extenda o Spatie seria overhead sem benefício — o critério de "CRUD simples sem lógica de query complexa" dispensa Repository e wrapper.

### Registo manual das Policies

As Policies existentes (`EntidadePolicy`, `CategoriaDocumentoPolicy`) são auto-discovered pelo Laravel porque os Models correspondentes (`Entidade`, `CategoriaDocumento`) têm `#[UsePolicy(...)]`. O Spatie `Role` é um Model externo — não podemos adicionar esse atributo. O `User` model é nosso, mas a `UtilizadorPolicy` tem nome não-convencional (não `UserPolicy`). Ambas precisam de registo explícito em `AppServiceProvider::boot()`:

```php
Gate::policy(Role::class, RolePolicy::class);
Gate::policy(User::class, UtilizadorPolicy::class);
```

**Atenção:** `User::class` já é usado implicitamente por outras Policies? Não — as Policies existentes são para `Entidade` e `CategoriaDocumento`, não para `User`. `Gate::policy(User::class, ...)` só afecta operações sobre o User como objecto (target), não operações feitas pelo User (actor). Sem conflito.

### IDs inteiros no route binding

O Spatie `Role` usa `bigint auto_increment` (confirmado na schema). O `User` também usa `int $id` (sem `HasUuids`). As rotas `{role}` e `{utilizador}` fazem binding por PK inteira — diferente dos outros models do projecto que usam UUID.

### `guard_name` fixo a `'web'`

O projecto usa um único guard (`web`). Sanctum autentica via middleware, não como guard separado. Todos os roles e permissions existentes usam `guard_name = 'web'`. Novos roles criados via API devem usar o mesmo valor fixo.

### EliminarRole — protecção de roles de sistema na Action

Roles `admin` e `utilizador` são roles de sistema — eliminá-los quebraria o seeder e os testes. A protecção acontece na `EliminarRoleAction` (não apenas no FormRequest) para garantir a invariante em qualquer contexto de invocação:

```php
if (in_array($role->name, ['admin', 'utilizador'], true)) {
    throw new \DomainException('Não é possível eliminar um role de sistema.');
}
```

### `syncRoles()` em `AtribuirRoleAction`

Um utilizador tem sempre um único role neste sistema. `syncRoles([$nomeRole])` garante que o role anterior é removido e o novo atribuído atomicamente. `assignRole()` acumula — não é o que queremos.

### Autorização dupla camada

Seguindo o padrão do projecto (`02-shared/padroes-acoes.md`): `Gate::authorize()` no `FormRequest::authorize()` (contexto HTTP) **e** no início do `handle()` da Action (contexto programático). O `Gate::authorize()` fica sempre **fora** da `DB::transaction()`.

---

## Ficheiros afectados

| Ficheiro | Operação |
|---|---|
| `app/Features/Role/RoleController.php` | Criar |
| `app/Features/Role/RoleResource.php` | Criar |
| `app/Features/Role/Listar/{Action,Request}.php` | Criar |
| `app/Features/Role/Ver/{Action,Request}.php` | Criar |
| `app/Features/Role/Criar/{Action,Request,Dto}.php` | Criar |
| `app/Features/Role/Actualizar/{Action,Request,Dto}.php` | Criar |
| `app/Features/Role/Eliminar/{Action,Request}.php` | Criar |
| `app/Features/Utilizador/UtilizadorController.php` | Criar |
| `app/Features/Utilizador/AtribuirRole/{Action,Request}.php` | Criar |
| `app/Policies/RolePolicy.php` | Criar |
| `app/Policies/UtilizadorPolicy.php` | Criar |
| `app/Providers/AppServiceProvider.php` | Actualizar — registar as 2 Policies |
| `database/migrations/2026_06_22_*_seed_roles_permissions_v2.php` | Criar |
| `database/seeders/RolesPermissionsSeeder.php` | Actualizar — novas permissions ao admin |
| `routes/api.php` | Adicionar 6 rotas |
| `tests/Feature/Features/Role/{5 ficheiros}.php` | Criar |
| `tests/Unit/Features/Role/{3 ficheiros}.php` | Criar |
| `tests/Feature/Features/Utilizador/AtribuirRoleTest.php` | Criar |
| `tests/Unit/Features/Utilizador/AtribuirRoleActionTest.php` | Criar |
| `docs/system_spec/01-features/role.md` | Criar |
| `docs/system_spec/01-features/utilizador.md` | Criar |
| `docs/system_spec/05-routes/role.md` | Criar |
| `docs/system_spec/04-infra/autorizacao.md` | Actualizar — novas permissions + matrix |
| `docs/system_spec/00-index.md` | Actualizar |

---

## Riscos identificados

| Risco | Mitigação |
|---|---|
| **Políticas não auto-discovered** — `RolePolicy` e `UtilizadorPolicy` com nomes não convencionais ou target externo | Registar explicitamente via `Gate::policy()` em `AppServiceProvider::boot()` |
| **Cache Spatie em testes** — permissions em cache de um teste contaminam o seguinte | `PermissionRegistrar::forgetCachedPermissions()` no `beforeEach` de todos os testes que manipulam roles/permissions |
| **`Gate::policy(User::class, UtilizadorPolicy::class)` pode conflituar** se no futuro existir outra Policy para User | Não há conflito actual; documentar que `UtilizadorPolicy` cobre acções sobre o User como target |
| **IDs inteiros vs UUID** — o frontend (ou testes) podem assumir UUID nos `{role}` e `{utilizador}` params | Documentar explicitamente nas rotas que estes recursos usam int ID |
| **`syncRoles()` remove roles existentes** — um bug na chamada pode deixar utilizador sem role | O Role é obrigatório no FormRequest (`required|string|exists:roles,name`); sem risco se a validação passar |

---

## Questões em aberto

Nenhuma — âmbito bem definido pela issue.

---

## Desvio documentado: sem Repository

`ListarRolesAction`, `VerRoleAction`, etc. acedem ao Spatie `Role` model directamente. Critério: CRUD simples, queries directas sem lógica partilhada entre Actions. Dispensado por definição canónica do projecto (`04-infra/repositories.md`).
