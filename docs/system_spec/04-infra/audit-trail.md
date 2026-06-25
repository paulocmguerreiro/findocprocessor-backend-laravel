# System Spec — Infra: Audit Trail

> Issue #54 | Branch: `feat/audit-trail-activitylog`

## Visão geral

Rastreio persistente e consultável de alterações de dados de domínio (quem alterou o quê, valores antes/depois), com atomicidade garantida pela transação da Action. Implementado com `spatie/laravel-activitylog ^4.0`.

Complementa o logging estruturado (`04-infra/logging.md`): o logging escreve para stdout/ficheiro (observabilidade operacional); o audit trail persiste em BD e é consultável por sujeito ou causer.

---

## Tabela `activity_log`

Migrada **antes** dos seeds de roles (timestamps `2026_06_22_14500x`) — ver "Ordem de migração" abaixo.

| Coluna | Tipo BD | Notas |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `log_name` | `string` nullable | nome do log (default) |
| `description` | `text` | evento textual |
| `subject_type` | `string` nullable | FQCN do sujeito (sem morphMap) |
| `subject_id` | **`char(36)`** nullable | acomoda UUID (CategoriaDocumento, Entidade) **e** bigint (Role) |
| `event` | `string` nullable | `created` / `updated` / `deleted` |
| `causer_type` | `string` nullable | `App\Models\User` |
| `causer_id` | `bigint` nullable | id do utilizador autenticado |
| `properties` | `json` nullable | `attributes` (novo) + `old` (antigo) |
| `batch_uuid` | `uuid` nullable | agrupamento de eventos |
| `created_at` / `updated_at` | `timestamp` | — |

> **Desvio à migration publicada:** o stub do pacote cria `subject_id` como `bigint` (`nullableMorphs`). Como os sujeitos são mistos (UUID + bigint), foi trocado por `nullableUuidMorphs('subject')` → `char(36)`. `causer` mantém `nullableMorphs` (User usa bigint). As 3 migrations foram modernizadas para as convenções do projecto (`strict_types`, classe anónima, return types).

### Ordem de migração

A infra de auditoria tem de existir **antes** de qualquer escrita auditada. Os seeds de roles (`2026_06_22_150715`) criam roles via Eloquent → o `RoleObserver` escreve em `activity_log`. Por isso as migrations de `activity_log` correm imediatamente antes dos seeds. Caso contrário, um `migrate` fresh falha com `no such table: activity_log`.

---

## Modelos auditados

### Via trait — `App\Models\Concerns\RegistaActividade`

Modelos próprios (`HasUuids`) usam o trait, que encapsula a política canónica do projecto:

```php
LogOptions::defaults()
    ->logFillable()
    ->logExcept($this->atributosExcluidosDaActividade())
    ->logOnlyDirty()
    ->dontSubmitEmptyLogs();
```

- **Centralização:** a política de auditoria vive num único sítio; alterá-la propaga a todos os modelos auditados.
- **Hook `atributosExcluidosDaActividade(): list<string>`** — devolve `[]` por omissão; modelos com campos sensíveis sobrepõem.

| Model | Trait | Campos excluídos |
|---|---|---|
| `CategoriaDocumento` | `RegistaActividade` | — |
| `Entidade` | `RegistaActividade` | `['nif']` (dado fiscal — RGPD) |

### Via Observer — `App\Observers\RoleObserver`

`Spatie\Permission\Models\Role` é modelo de terceiro — não pode usar o trait. Auditado por Observer registado em `AppServiceProvider::boot()`:

```php
Role::observe(RoleObserver::class);
```

- `created` / `deleted`: `activity()->performedOn($role)->event(...)->log(...)`
- `updated`: `withProperties(['old' => getOriginal(), 'attributes' => getAttributes()])`. Sem guard a `getDirty()` — o evento `updated` do Eloquent só dispara quando há alterações reais.
- Sem alterações a `config/permission.php`.

---

## Atomicidade

O pacote regista via listeners de eventos Eloquent (`created`/`updated`/`deleted`) que disparam **dentro** da `DB::transaction()` das Actions. Rollback da transação → rollback do registo de actividade. O pacote **não** usa filas por omissão (`config/activitylog.php` não publicado; default v4 não enfileira). Verificado por testes de rollback.

---

## Causer

O `causer` é associado automaticamente ao `Auth::user()` pelo `ActivitylogServiceProvider`, a partir do guard autenticado. Nenhuma configuração nas Actions. Em contexto sem autenticação (seeds, console) o causer é `null`.

---

## Campos sensíveis

| Model | Campo | Razão |
|---|---|---|
| `Entidade` | `nif` | Dado fiscal — RGPD |

`User` nunca é sujeito de auditoria — apenas causer.

---

## Retenção (pendente)

NIS2 Art. 21 sugere retenção mínima de 12 meses. Não implementado nesta issue — candidato a issue separada (purga agendada de `activity_log`).

---

## Testes

- **Unit** (`tests/Unit/Features/AuditTrail/`): `created`/`updated`/`deleted`, no-op (logOnlyDirty), rollback, e exclusão de `nif` (Entidade).
- **Feature**: assertions `Activity::count()` adicionadas aos testes HTTP de escrita existentes de CategoriaDocumento, Entidade e Role (incl. causer não-null e 403 → 0).
- Cada ficheiro limpa `activity_log` no `beforeEach` (os seeds deixam registos persistentes fora da transação do teste).
