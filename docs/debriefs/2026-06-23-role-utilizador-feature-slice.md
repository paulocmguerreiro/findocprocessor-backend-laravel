# Debrief — Issue #50: Role + Utilizador — feature slice

**Data:** 2026-06-23
**Branch:** `feat/role-utilizador-feature-slice`
**Commits:** 16 commits

---

## O que foi implementado

Feature slice completa de gestão de roles e atribuição de roles a utilizadores:

- **Migration** `2026_06_23_*_seed_roles_permissions_v2.php` — 5 novas permissions (`roles.ver`, `roles.criar`, `roles.actualizar`, `roles.eliminar`, `utilizadores.atribuir-role`) atribuídas ao role `admin`
- **`RolePolicy`** + **`UtilizadorPolicy`** — autorização por permission granular
- **`AppServiceProvider`** — `Gate::policy(Role::class, RolePolicy::class)`; `UtilizadorPolicy` registada via `#[UsePolicy]` no modelo `User`
- **Exception handler** — `DomainException` → 422 com `$e->getMessage()` (antes do handler genérico `Throwable`)
- **Feature Role** — Listar, Ver, Criar, Actualizar, Eliminar (`CampoOrdenacaoRoles`, `RoleResource`, Request + DTO + Action por caso de uso)
- **Feature Utilizador** — `AtribuirRole` (Request + Action)
- **`RoleController`** + **`UtilizadorController`** — sem lógica; apenas dispatch para Actions
- **Rotas** — `apiResource('roles')` + `PUT utilizadores/{utilizador}/role`
- **45 testes** — 17 Unit + 28 Feature; 277 testes totais; 100% cobertura

---

## Decisões tomadas

### 1. `UtilizadorPolicy` via `#[UsePolicy]` no modelo, não no `AppServiceProvider`

A spec original previa `Gate::policy(User::class, UtilizadorPolicy::class)` no `AppServiceProvider`. O utilizador optou por usar o atributo PHP `#[UsePolicy(UtilizadorPolicy::class)]` directamente no modelo `User`, que é a abordagem idiomática do Laravel 13. O `AppServiceProvider` ficou apenas com o registo de `RolePolicy` (Spatie `Role` não é um modelo da aplicação, pelo que não tem atributo `#[UsePolicy]`).

### 2. Guard de auto-modificação de role — invariante de domínio

Adicionado em `AtribuirRoleAction`: mesmo um utilizador com permissão `utilizadores.atribuir-role` não pode alterar o próprio role. Implementado como `DomainException` (→ 422) na Action, não na Policy — porque é uma regra de domínio (prevenção de auto-bloqueio), não de autorização.

**Motivo:** um director com acesso total poderia por engano remover o próprio role de admin, bloqueando-se a si próprio. O guard funciona em qualquer contexto de invocação (HTTP e directo).

### 3. `findByName('admin', 'web')` — guard explícito nos testes Feature

Nos testes Feature o guard activo muda para `sanctum` (contexto HTTP com Sanctum). `Role::findByName('admin')` sem guard explícito lança `RoleDoesNotExist`. Corrigido com `findByName('admin', 'web')` em todos os ficheiros de teste afectados.

### 4. `EliminarRoleAction` — `void` em vez de `bool` na closure de transação

`$role->delete()` retorna `bool|null`. A closure original tipava `: bool`, o que causava erro Intelephense. Corrigido para `function (): void { $role->delete(); }` — o valor de retorno do `delete()` não é necessário.

### 5. T2 (Seeder) — não aplicável

O seeder actual não tem lista `$todasPermissions` — as permissions são geridas via data migrations. O role `admin` herda as 5 novas permissions via T1 migration. Tarefa ignorada por não ter impacto.

### 6. PHPDoc `RoleResource` — tipos reais do Spatie Role

`id` é `int|string` (tipo base do modelo Spatie, não `int`) e `permissoes` é `array<int, mixed>` (Eloquent `pluck()` sem tipo genérico declarado). PHPDoc actualizado para reflectir os tipos reais que PHPStan infere — não é widening, é precisão.

---

## O que não foi implementado / divergências

- **`RolesPermissionsSeeder`** — não actualizado (ver decisão §5)
- **docs system_spec** criados/actualizados nesta fase 3a (não na fase 2)

---

## Aprendizagens

### Vertical Slice — a Action como fronteira de contexto

A decisão de colocar o guard `auth()->id() === $utilizador->id` na **Action** (e não na Policy) clarificou um ponto importante de VSA: a Policy define *quem tem acesso a quê*, mas a Action define *o que é permitido fazer*. Uma regra como "nunca alterar o próprio role" não é sobre autorização — é uma invariante do domínio que precisa de ser garantida em qualquer contexto de invocação (HTTP, Job, Artisan). A Action é o lugar certo.

### Guard explícito em testes Feature com Sanctum

Quando o Sanctum está activo como middleware, o contexto HTTP usa o guard `sanctum`. O Spatie Permission resolve os roles/permissions usando o guard padrão do `config/auth.php` (que é `web`), mas `findByName()` sem argumento de guard usa o guard "por omissão" que em contexto Feature test pode ser `sanctum`. A regra prática: sempre passar `'web'` explicitamente em `findByName()` nos testes.

### `#[UsePolicy]` vs `AppServiceProvider`

Laravel 13 suporta o atributo `#[UsePolicy(MyPolicy::class)]` directamente no modelo — é mais explícito e não polui o `AppServiceProvider`. Para modelos de terceiros (como `Spatie\Permission\Models\Role`) onde não se pode editar o modelo, continua a ser necessário o `Gate::policy()` no provider.

### DomainException no exception handler — ordem importa

O Laravel processa handlers de excepção por ordem de registo. O `Throwable` genérico captura qualquer excepção, incluindo `DomainException`. O handler específico tem de ser registado **antes** do genérico para ter efeito. Lição: ao adicionar handlers ao `withExceptions()`, verificar sempre a ordem relativa ao handler catch-all.
