# Brief — Issue #36: Autorização por roles/permissions

**Issue:** #36 — `feat(auth): autorização por roles/permissions — Spatie Laravel Permission + Policies`
**Data:** 2026-06-22
**Slug:** `autorizacao-roles-permissions`
**Depende de:** #35 (Sanctum) — concluído

---

## Contexto

A issue #35 implementou autenticação via Bearer tokens (Sanctum). As Policies existentes (`EntidadePolicy`, `CategoriaDocumentoPolicy`) são stubs que retornam `true` para todos os métodos — não há verificação real de autorização. Qualquer utilizador autenticado pode criar, actualizar ou eliminar qualquer recurso.

Esta issue instala `spatie/laravel-permission`, define roles e permissions, e implementa as Policies com verificação real via `hasPermissionTo()`.

---

## Âmbito

1. Instalar `spatie/laravel-permission` como dependência de produção
2. Publicar e executar as migrations do Spatie (`roles`, `permissions`, `model_has_roles`, etc.)
3. Adicionar `HasRoles` ao model `User`
4. Criar `RolesPermissionsSeeder` com roles, permissions e utilizador admin de desenvolvimento
5. Implementar `EntidadePolicy` e `CategoriaDocumentoPolicy` com `$utilizador->hasPermissionTo()`
6. Testes Feature: 403 para role sem permissão; 200 para role autorizado

---

## Decisões técnicas

### Matriz de permissions

8 permissions no total — 4 por recurso:

| Permission | Admin | Utilizador |
|---|---|---|
| `entidades.ver` | ✓ | ✓ |
| `entidades.criar` | ✓ | ✗ |
| `entidades.actualizar` | ✓ | ✗ |
| `entidades.eliminar` | ✓ | ✗ |
| `categorias-documento.ver` | ✓ | ✓ |
| `categorias-documento.criar` | ✓ | ✗ |
| `categorias-documento.actualizar` | ✓ | ✗ |
| `categorias-documento.eliminar` | ✓ | ✗ |

**Justificação:** `utilizador` tem acesso de leitura (consulta de entidades e categorias para selecção futura em documentos). Criação, actualização e eliminação são privilégio exclusivo de `admin`. Esta distinção torna os testes de 403 concretos e verificáveis.

### Policy check: `hasPermissionTo()` em vez de `hasRole()`

Mais granular — permite atribuir permissions individuais a utilizadores sem alterar o seu role. Mais fácil de expandir quando surgirem roles intermédios.

### Mapeamento Policy → Permission

| Método Policy | Permission |
|---|---|
| `viewAny()` | `*.ver` |
| `view()` | `*.ver` |
| `create()` | `*.criar` |
| `update()` | `*.actualizar` |
| `delete()` | `*.eliminar` |

`ConverterEmEmpresaMaeRequest` usa `Gate::authorize('update', ...)` — coberto por `entidades.actualizar`.

### `User $utilizador` não-nullable nas Policies

Todas as rotas estão sob `auth:sanctum` — nunca chegam guests às Policies. Manter `User` não-nullable (correcto e consistente com o estado actual).

### Seeder de desenvolvimento

`RolesPermissionsSeeder` separado invocado pelo `DatabaseSeeder`. Cria:
1. Permissions (8)
2. Roles (`admin`, `utilizador`) com permissions atribuídas
3. Utilizador admin: `admin@findocprocessor.test` / password `password` com token Sanctum

---

## Ficheiros afectados

| Ficheiro | Operação |
|---|---|
| `composer.json` | Adicionar `spatie/laravel-permission` em `require` |
| `config/permission.php` | Publicado pelo Spatie (`artisan vendor:publish`) |
| `database/migrations/*_create_permission_tables.php` | Publicado pelo Spatie |
| `app/Models/User.php` | Adicionar `HasRoles` + `@property-read` roles/permissions |
| `app/Policies/EntidadePolicy.php` | Substituir `return true` por `hasPermissionTo()` |
| `app/Policies/CategoriaDocumentoPolicy.php` | Substituir `return true` por `hasPermissionTo()` |
| `database/seeders/RolesPermissionsSeeder.php` | Criar — roles, permissions, utilizador admin |
| `database/seeders/DatabaseSeeder.php` | Invocar `RolesPermissionsSeeder` |
| `docs/system_spec/03-models/user.md` | Adicionar `HasRoles`, relações roles/permissions |
| `docs/system_spec/04-infra/autorizacao.md` | Criar — Spatie, roles, permissions, seeder |
| `docs/system_spec/00-index.md` | Adicionar entrada para `04-infra/autorizacao.md` |

---

## Riscos identificados

| Risco | Mitigação |
|---|---|
| **Spatie cache de permissions em testes** — cache pode fazer um teste influenciar o seguinte | `PermissionRegistrar::forgetCachedPermissions()` no `setUp()` de cada ficheiro de teste que crie roles/permissions; ou via `RefreshDatabase` (já força re-seed em cada teste) |
| **Type coverage 100%** — métodos do trait `HasRoles` usam tipos complexos que o Pest type-coverage pode não inferir | Verificar se `@phpstan-ignore` é necessário; correr `composer test:type-coverage` após cada alteração ao User model |
| **Migrations Spatie em SQLite** — SQLite não suporta todos os tipos de coluna | Spatie é compatível com SQLite por design; sem risco esperado, mas verificar no primeiro `php artisan migrate` em modo de teste |
| **ArchTest `strict_types`** — ficheiros publicados pelo Spatie não têm `strict_types=1` | Ficheiros publicados ficam em `database/migrations/` e `config/` — não estão no namespace `App\`, logo o ArchTest `expect('App')->toUseStrictTypes()` não os apanha |

---

## Questões em aberto

Nenhuma — âmbito bem definido pela issue.

---

## Desvio documentado: sem Repository no Seeder

`RolesPermissionsSeeder` acede a `Role::create()` e `Permission::create()` directamente — contexto de setup/seed, não de domínio. Dispensa Repository por critério de CRUD simples e contexto não-HTTP.
