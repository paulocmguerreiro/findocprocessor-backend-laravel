# Plano — Issue #73: Utilizador Restaurar + RGPD Anonimização

**Data:** 2026-07-01
**Slug:** `utilizador-restaurar-anonimizar-rgpd`
**Spec:** `docs/specs/2026-07-01-utilizador-restaurar-anonimizar-rgpd.md`

---

## Tarefas

### T1 — Migration: permissão `utilizadores.anonimizar`
- Criar `database/migrations/2026_07_01_<ts>_seed_utilizadores_anonimizar_permission.php`
- `up()`: `forgetCachedPermissions()` → `Permission::create('utilizadores.anonimizar')` → `admin->givePermissionTo(...)`
- `down()`: `forgetCachedPermissions()` → `Permission::findByName(...)->delete()`
- Correr `php artisan migrate` para aplicar

### T2 — Model `User`: adicionar `RegistaActividade`
- Adicionar trait `RegistaActividade` a `app/Models/User.php`
- Adicionar método `atributosExcluidosDaActividade(): array` → `['password', 'remember_token']`
- Actualizar `@property-read` se necessário

### T3 — Policy: `restore()` e `anonimizar()`
- Adicionar `restore(User $autenticado, User $alvo): bool` → `hasPermissionTo('utilizadores.eliminar')`
- Adicionar `anonimizar(User $autenticado, User $alvo): bool` → `hasPermissionTo('utilizadores.anonimizar')`

### T4 — Parte A: `RestaurarUtilizadorAction` + `RestaurarUtilizadorRequest`
- Criar `app/Features/Utilizador/Restaurar/RestaurarUtilizadorRequest.php`
- Criar `app/Features/Utilizador/Restaurar/RestaurarUtilizadorAction.php`
  - Assinatura: `handle(User|int $utilizador): User`
  - `Gate::authorize('restore', ...)` fora da transação
  - Invariante 1: `! trashed()` → `DomainException`
  - Invariante 2: `str_starts_with($email, 'anonimizado+')` → `DomainException`
  - `DB::transaction`: `restore()` + `invalidarCache(TagCache::Utilizadores)`
  - Retorna `$utilizador->load('roles')`

### T5 — Parte B: `AnonimizarUtilizadorAction` + `AnonimizarUtilizadorRequest`
- Criar `app/Features/Utilizador/Anonimizar/AnonimizarUtilizadorRequest.php`
- Criar `app/Features/Utilizador/Anonimizar/AnonimizarUtilizadorAction.php`
  - Assinatura: `handle(User $utilizador): void`
  - `Gate::authorize('anonimizar', ...)` fora da transação
  - Invariante 1: auto-anonimização → `DomainException` (fora da transação)
  - Invariante 2: já anonimizado → `DomainException` (fora da transação)
  - `DB::transaction`: `tokens()->delete()` → `forceFill()->saveQuietly()` → `activity()->performedOn()->event('rgpd.anonimizacao')->log(...)` → `delete()` → `invalidarCache`

### T6 — Controller + Rotas
- Adicionar `restaurar()` e `anonimizar()` ao `UtilizadorController`
- Adicionar rotas a `routes/api.php` dentro do grupo `auth:sanctum`:
  ```php
  Route::patch('utilizadores/{utilizador}/restaurar', [UtilizadorController::class, 'restaurar'])->withTrashed();
  Route::post('utilizadores/{utilizador}/anonimizar',  [UtilizadorController::class, 'anonimizar']);
  ```

### T7 — Testes Unit: `RestaurarUtilizadorActionTest`
- `tests/Unit/Features/Utilizador/RestaurarUtilizadorActionTest.php`
- Cenários: restaura com User directo / com int PK / não-trashed → DomainException / anonimizado → DomainException / rollback / sem permissão → AuthorizationException / guest → AuthorizationException

### T8 — Testes Unit: `AnonimizarUtilizadorActionTest`
- `tests/Unit/Features/Utilizador/AnonimizarUtilizadorActionTest.php`
- Cenários: anonimiza dados + soft-delete + tokens revogados / auto-anonimização → DomainException / já anonimizado → DomainException / rollback / sem permissão → AuthorizationException / guest → AuthorizationException
- Asserção audit: `Activity::where('event', 'rgpd.anonimizacao')->exists()` sem campos PII

### T9 — Testes Feature: `RestaurarUtilizadorTest`
- `tests/Feature/Features/Utilizador/RestaurarUtilizadorTest.php`
- Cenários: 200 + `deleted_at: null` / 422 não-trashed / 422 anonimizado / 404 inexistente / 403 sem permissão / 401 guest / restaurado aparece em GET /utilizadores

### T10 — Testes Feature: `AnonimizarUtilizadorTest`
- `tests/Feature/Features/Utilizador/AnonimizarUtilizadorTest.php`
- Cenários: 204 + dados anonimizados em BD + soft-deleted + tokens revogados / 422 auto / 422 já-anonimizado / 404 inexistente / 403 sem permissão / 401 guest / token do utilizador anonimizado → 401

### T11 — Qualidade + system_spec
- `composer lint && composer refactor`
- `composer test` — pipeline completa (zero erros)
- Actualizar `docs/system_spec/01-features/utilizador.md` — novas Actions + Policy methods
- Actualizar `docs/system_spec/05-routes/role.md` — novas rotas
- Actualizar `docs/system_spec/04-infra/autorizacao.md` — permissão `utilizadores.anonimizar` + matriz
- Actualizar `docs/system_spec/04-infra/audit-trail.md` — `User` na tabela de modelos auditados

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8 → T9 → T10 → T11
```

Dependências sequenciais: T1 (permissão) antes de T3/T5 (policy/action usam a permissão em testes); T2 (trait no model) antes de T7–T10 (testes verificam audit).

---

## Commit por tarefa

Cada tarefa produz um commit atómico antes de avançar:

| Tarefa | Mensagem de commit |
|---|---|
| T1 | `feat(auth): adicionar permissão utilizadores.anonimizar — Issue #73` |
| T2 | `feat(model): adicionar RegistaActividade ao User — Issue #73` |
| T3 | `feat(policy): restore() e anonimizar() na UtilizadorPolicy — Issue #73` |
| T4 | `feat(action): RestaurarUtilizadorAction + Request — Issue #73` |
| T5 | `feat(action): AnonimizarUtilizadorAction + Request — Issue #73` |
| T6 | `feat(routes): restaurar + anonimizar no UtilizadorController e api.php — Issue #73` |
| T7 | `test(unit): RestaurarUtilizadorActionTest — Issue #73` |
| T8 | `test(unit): AnonimizarUtilizadorActionTest — Issue #73` |
| T9 | `test(feature): RestaurarUtilizadorTest — Issue #73` |
| T10 | `test(feature): AnonimizarUtilizadorTest — Issue #73` |
| T11 | `docs(system_spec): utilizador + rotas + autorizacao + audit-trail — Issue #73` |
