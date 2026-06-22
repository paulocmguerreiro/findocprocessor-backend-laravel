# Plano — Issue #50: Role + Utilizador — feature slice

**Issue:** #50
**Branch:** `feat/role-utilizador-feature-slice`
**Spec:** `docs/specs/2026-06-22-role-utilizador-feature-slice.md`

---

## T1 — Migration de novas permissions

Criar `database/migrations/2026_06_22_HHMMSS_seed_roles_permissions_v2.php` conforme spec §1.

Verificar: `php artisan migrate` sem erros; permissions existem na tabela `permissions`; role `admin` tem as 5 novas permissions via `role_has_permissions`.

---

## T2 — Actualizar RolesPermissionsSeeder

Ficheiro: `database/seeders/RolesPermissionsSeeder.php`

Adicionar as 5 novas permissions à lista `$todasPermissions` do seeder de desenvolvimento (conforme spec §2).

---

## T3 — Actualizar AppServiceProvider

Ficheiro: `app/Providers/AppServiceProvider.php`

Adicionar `Gate::policy(Role::class, RolePolicy::class)` e `Gate::policy(User::class, UtilizadorPolicy::class)` no `boot()` (conforme spec §3).

Verificar: `composer test:types` — Larastan sem erros.

---

## T4 — DomainException no exception handler

Ficheiro: `bootstrap/app.php`

Adicionar handler para `\DomainException` → 422 com `$e->getMessage()` (conforme spec §17).

---

## T5 — Policies

Criar `app/Policies/RolePolicy.php` (spec §4) e `app/Policies/UtilizadorPolicy.php` (spec §5).

Correr `composer lint` após criar.

---

## T6 — RoleResource + CampoOrdenacaoRoles

Criar:
- `app/Features/Role/RoleResource.php` (spec §6)
- `app/Features/Role/Listar/CampoOrdenacaoRoles.php` (spec §7)

---

## T7 — Feature Role — Listar

Criar:
- `app/Features/Role/Listar/ListarRolesRequest.php`
- `app/Features/Role/Listar/ListarRolesAction.php`

(spec §7)

---

## T8 — Feature Role — Ver

Criar:
- `app/Features/Role/Ver/VerRoleRequest.php`
- `app/Features/Role/Ver/VerRoleAction.php`

(spec §8)

---

## T9 — Feature Role — Criar

Criar:
- `app/Features/Role/Criar/CriarRoleRequest.php`
- `app/Features/Role/Criar/CriarRoleDto.php`
- `app/Features/Role/Criar/CriarRoleAction.php`

(spec §9)

---

## T10 — Feature Role — Actualizar

Criar:
- `app/Features/Role/Actualizar/ActualizarRoleRequest.php`
- `app/Features/Role/Actualizar/ActualizarRoleDto.php`
- `app/Features/Role/Actualizar/ActualizarRoleAction.php`

(spec §10)

---

## T11 — Feature Role — Eliminar

Criar:
- `app/Features/Role/Eliminar/EliminarRoleRequest.php`
- `app/Features/Role/Eliminar/EliminarRoleAction.php`

(spec §11)

---

## T12 — Feature Utilizador — AtribuirRole

Criar:
- `app/Features/Utilizador/AtribuirRole/AtribuirRoleRequest.php`
- `app/Features/Utilizador/AtribuirRole/AtribuirRoleAction.php`

(spec §12)

---

## T13 — Controllers

Criar:
- `app/Features/Role/RoleController.php` (spec §13)
- `app/Features/Utilizador/UtilizadorController.php` (spec §14)

---

## T14 — Rotas

Ficheiro: `routes/api.php`

Adicionar (dentro do grupo `auth:sanctum`):
```php
Route::apiResource('roles', RoleController::class);
Route::put('utilizadores/{utilizador}/role', [UtilizadorController::class, 'atribuirRole']);
```

Verificar: `php artisan route:list --path=roles --path=utilizadores`

---

## T15 — Testes Unit

Criar (spec §16.7 a §16.10):
- `tests/Unit/Features/Role/CriarRoleActionTest.php`
- `tests/Unit/Features/Role/ActualizarRoleActionTest.php`
- `tests/Unit/Features/Role/EliminarRoleActionTest.php`
- `tests/Unit/Features/Utilizador/AtribuirRoleActionTest.php`

Correr `composer test` após cada ficheiro.

---

## T16 — Testes Feature

Criar (spec §16.1 a §16.6):
- `tests/Feature/Features/Role/ListarRolesTest.php`
- `tests/Feature/Features/Role/VerRoleTest.php`
- `tests/Feature/Features/Role/CriarRoleTest.php`
- `tests/Feature/Features/Role/ActualizarRoleTest.php`
- `tests/Feature/Features/Role/EliminarRoleTest.php`
- `tests/Feature/Features/Utilizador/AtribuirRoleTest.php`

Correr `composer test` após cada ficheiro.

---

## T17 — Qualidade e system_spec

```bash
composer lint       # Pint — formatação
composer refactor   # Rector — modernizações
composer test       # pipeline completa
```

Criar/actualizar docs de system_spec:
- `docs/system_spec/01-features/role.md` (criar)
- `docs/system_spec/01-features/utilizador.md` (criar)
- `docs/system_spec/05-routes/role.md` (criar)
- `docs/system_spec/04-infra/autorizacao.md` (actualizar — novas permissions + matriz)
- `docs/system_spec/00-index.md` (actualizar)

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8 → T9 → T10 → T11 → T12 → T13 → T14 → T15 → T16 → T17
```

Dependências críticas: T1 (migration) antes de T15/T16 (testes precisam das permissions na BD).
T5 (Policies) antes de T7–T12 (Actions fazem Gate::authorize com as Policies).
T3 (AppServiceProvider) antes de qualquer teste de autorização.
