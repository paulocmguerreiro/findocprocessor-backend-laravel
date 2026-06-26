# System Spec — Model: Role

> `Spatie\Permission\Models\Role` (modelo de terceiro)

Role do sistema de autorização (Spatie Permission). **Não há subclasse própria** — usa-se directamente o modelo do pacote. A gestão (CRUD) está na feature `Role` (`01-features/role.md`); a autorização em `04-infra/autorizacao.md`.

**Tabela:** `roles` (`id` bigint autoincrement, `name`, `guard_name`, timestamps)

---

## Audit trail via Observer

Como `Role` é de terceiro, não pode usar o trait `RegistaActividade`. A auditoria é feita por `App\Observers\RoleObserver`, registado em `AppServiceProvider::boot()` com `Role::observe(RoleObserver::class)`.

| Evento | Comportamento |
|---|---|
| `created` | `activity()->performedOn($role)->event('created')->log('created')` |
| `updated` | regista `old` (getOriginal) + `attributes` (getAttributes); o evento Eloquent só dispara em alterações reais |
| `deleted` | `activity()->performedOn($role)->event('deleted')->log('deleted')` |

- `subject_id` de Role é bigint, guardado na coluna `char(36)` de `activity_log` (ver `04-infra/audit-trail.md`).
- O Observer corre dentro da `DB::transaction()` das Actions de Role — atomicidade garantida.

> Detalhe completo: `04-infra/audit-trail.md`.

---

## Policy

Como `Role` é modelo de terceiro, **não** suporta `#[UsePolicy]`. A `RolePolicy` é registada manualmente via `Gate::policy(Role::class, RolePolicy::class)` no `AppServiceProvider::boot()`. `hasPermissionTo('roles.<accao>')` por ability. Detalhe em `04-infra/autorizacao.md`.
