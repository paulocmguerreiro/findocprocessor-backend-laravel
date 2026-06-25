# Plano — Audit Trail com spatie/laravel-activitylog

**Issue:** #54
**Branch:** `feat/audit-trail-activitylog`
**Spec:** `docs/specs/2026-06-25-audit-trail-activitylog.md`

---

## Tarefas

### T1 — Publicar e aplicar migration do activitylog

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

Verificar que a tabela `activity_log` foi criada com as colunas esperadas.

**Commit:** `feat(infra): publicar e aplicar migration activity_log — Issue #54`

---

### T2 — Adicionar `LogsActivity` a `CategoriaDocumento`

Ficheiro: `app/Models/CategoriaDocumento.php`

- Adicionar `use LogsActivity;`
- Implementar `getActivitylogOptions()` com `logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()`
- Actualizar `@property-read` se necessário
- Correr `composer test:types` para verificar Larastan

**Commit:** `feat(model): LogsActivity em CategoriaDocumento — Issue #54`

---

### T3 — Adicionar `LogsActivity` a `Entidade`

Ficheiro: `app/Models/Entidade.php`

- Adicionar `use LogsActivity;`
- Implementar `getActivitylogOptions()` com `logFillable()->logExcept(['nif'])->logOnlyDirty()->dontSubmitEmptyLogs()`
- Correr `composer test:types`

**Commit:** `feat(model): LogsActivity em Entidade (logExcept nif) — Issue #54`

---

### T4 — Criar `RoleObserver` e registar em `AppServiceProvider`

Ficheiros:
- Criar `app/Observers/RoleObserver.php` — `created`, `updated` (com `getDirty()`), `deleted`
- Actualizar `app/Providers/AppServiceProvider.php` — `Role::observe(RoleObserver::class)` em `boot()`
- Correr `composer test:types`

**Commit:** `feat(infra): RoleObserver para audit trail de Role — Issue #54`

---

### T5 — Testes Unit (padrão dual — camada programática)

Ficheiros a criar em `tests/Unit/Features/AuditTrail/`:
- `CategoriaDocumentoActivityTest.php`
- `EntidadeActivityTest.php`
- `RoleActivityTest.php`

Cenários por ficheiro:
- `created` → `Activity::count() === 1`, `event === 'created'`, subject correcto
- `updated` com alterações → `Activity::count() === 1`, `properties.old/new` correctos
- `updated` sem alterações → `Activity::count() === 0` (logOnlyDirty)
- `deleted` → `Activity::count() === 1`, `event === 'deleted'`
- rollback → model event lança excepção dentro de transação → `Activity::count() === 0`

Cenário adicional para `EntidadeActivityTest`:
- `nif` não aparece em `properties.attributes` nem `properties.old`

Correr `composer test` após esta tarefa.

**Commit:** `test(unit): audit trail — CategoriaDocumento, Entidade, Role — Issue #54`

---

### T6 — Testes Feature (adicionar assertions aos testes HTTP existentes)

Adicionar `Activity::count()` assertions nos testes HTTP de escrita existentes:

| Ficheiro | Cenário a actualizar |
|---|---|
| `tests/Feature/Features/CategoriaDocumento/` | criar (201), actualizar (200), eliminar (200), 403 |
| `tests/Feature/Features/Entidade/` | criar (201), actualizar (200), eliminar (200), 403 |
| `tests/Feature/Features/Role/` | criar (201), actualizar (200), eliminar (200), 403 |

Correr `composer test` após esta tarefa.

**Commit:** `test(feature): assertions Activity::count() nos testes HTTP existentes — Issue #54`

---

### T7 — system_spec + lint/refactor final

- Criar `docs/system_spec/04-infra/audit-trail.md`
- Actualizar `docs/system_spec/00-index.md` — entrada na secção Infra
- Actualizar `docs/system_spec/03-models/categoria-documento.md` — LogsActivity
- Actualizar `docs/system_spec/03-models/entidade.md` — LogsActivity + logExcept
- Criar `docs/system_spec/03-models/role.md` — Observer pattern
- Correr `composer lint && composer refactor`
- Correr `composer test` (pipeline completa — zero erros)

**Commit:** `docs(system_spec): audit-trail.md + modelos actualizados — Issue #54`

---

## Ordem de execução

```
T1 → T2 → T3 → T4 → T5 → T6 → T7
```

T2 e T3 podem ser feitas em paralelo; T5 depende de T2+T3+T4.

---

## Riscos de implementação

| Tarefa | Risco | Acção |
|---|---|---|
| T2/T3 | Larastan flag `mixed` interno do trait | Testar `composer test:types` após cada model |
| T4 | Observer não corre dentro da transação | Verificar com rollback test em T5 |
| T5 | ArchTest flag `AuditTrail` como namespace desconhecido | Adicionar ao `.ignoring()` se necessário |
| T6 | Testes existentes sem `RefreshDatabase` isolado | Confirmar que cada teste limpa `activity_log` |
