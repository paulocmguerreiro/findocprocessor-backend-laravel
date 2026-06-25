# Spec — Audit Trail com spatie/laravel-activitylog

**Issue:** #54
**Branch:** `feat/audit-trail-activitylog`

---

## Contrato de comportamento

### CA-01: Instalação e migração

```bash
# Já instalado em Fase 1
composer require spatie/laravel-activitylog:"^4.0"

# Publicar e aplicar migration
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

Tabela criada: `activity_log` com colunas `log_name`, `description`, `subject_type`, `subject_id`, `causer_type`, `causer_id`, `properties` (JSON), `event`, `batch_uuid`.

---

### CA-02: Modelos auditados — API do pacote

O trait `Spatie\Activitylog\Traits\LogsActivity` exige a implementação de `getActivitylogOptions(): LogOptions`.

**Padrão canónico por model:**

```php
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
}
```

**Entidade** — adiciona `->logExcept(['nif'])`:

```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logExcept(['nif'])
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
}
```

Eventos registados por omissão: `created`, `updated`, `deleted`.

---

### CA-03: Auditoria de `Role` via Observer

`Spatie\Permission\Models\Role` é modelo de terceiro — em vez de subclasse, usa-se um Observer registado directamente sobre ele. Sem alterações a `config/permission.php`.

```php
// app/Observers/RoleObserver.php
final class RoleObserver
{
    public function created(Role $role): void
    {
        activity()->performedOn($role)->event('created')->log('created');
    }

    public function updated(Role $role): void
    {
        if (empty($role->getDirty())) {
            return;
        }
        activity()
            ->performedOn($role)
            ->event('updated')
            ->withProperties(['old' => $role->getOriginal(), 'attributes' => $role->getAttributes()])
            ->log('updated');
    }

    public function deleted(Role $role): void
    {
        activity()->performedOn($role)->event('deleted')->log('deleted');
    }
}
```

Registado em `AppServiceProvider::boot()`:

```php
Role::observe(RoleObserver::class);
```

O Observer corre dentro da `DB::transaction()` das Actions existentes — atomicidade garantida.

---

### CA-04: causer automático

O pacote liga `Auth::user()` como `causer` automaticamente via `ActivitylogServiceProvider`. Nenhuma configuração adicional necessária nas Actions — a associação é feita pelo pacote a partir do guard configurado.

---

### CA-05: Atomicidade com `DB::transaction()`

O pacote usa listeners de eventos Eloquent (`created`, `updated`, `deleted`) que disparam **dentro** da transação Laravel por omissão. Se a transação sofrer rollback, o registo de actividade também é revertido.

Confirmado no vendor: o pacote não usa filas (`queue`) por omissão — `config/activitylog.php` tem `'queue' => false`.

---

### CA-06: Campos sensíveis excluídos

| Model | Campo excluído | Razão |
|---|---|---|
| `Entidade` | `nif` | Dado fiscal — RGPD |
| `User` | não é sujeito — só causer | fora de âmbito |

---

### CA-07: Testes — padrão dual

#### Unit (`tests/Unit/Features/`)

Localização: `tests/Unit/Features/AuditTrail/`

Ficheiros:
- `CategoriaDocumentoActivityTest.php`
- `EntidadeActivityTest.php`
- `RoleActivityTest.php`

Cenários obrigatórios por model:

| Cenário | Verificação |
|---|---|
| `created` | `Activity::count() === 1`, `event === 'created'`, `subject_type = ModelClass` |
| `updated` | `Activity::count() === 1`, `event === 'updated'`, `properties.old/new` correctos |
| `deleted` | `Activity::count() === 1`, `event === 'deleted'` |
| `updated` sem alterações | `Activity::count() === 0` (logOnlyDirty + dontSubmitEmptyLogs) |
| rollback | model event lança excepção dentro de transação → `Activity::count() === 0` |

Cenário específico de `Entidade`:
- `nif` não aparece em `properties.old` nem `properties.new`

#### Feature (`tests/Feature/Features/`)

Localização: usar os testes HTTP existentes das features `CategoriaDocumento`, `Entidade`, `Role` — **não criar ficheiros duplicados**. Adicionar assertions nos testes de escrita existentes (criar, actualizar, eliminar).

Assertions a adicionar nos testes HTTP existentes:

```php
// após POST 201
expect(Activity::count())->toBe(1)
    ->and(Activity::first()->event)->toBe('created');

// após 403
expect(Activity::count())->toBe(0);
```

---

### CA-08: system_spec

- Criar `docs/system_spec/04-infra/audit-trail.md`
- Actualizar `docs/system_spec/00-index.md` — adicionar entrada na secção Infra
- Actualizar `docs/system_spec/03-models/categoria-documento.md` — adicionar LogsActivity
- Actualizar `docs/system_spec/03-models/entidade.md` — adicionar LogsActivity + logExcept
- Criar `docs/system_spec/03-models/role.md` — documentar App\Models\Role

---

## Componentes NÃO afectados

- Rotas: nenhuma nova rota (`openapi.yaml` não muda)
- Actions existentes: sem alterações (o log é automático via trait)
- FormRequests: sem alterações
- Jobs: sem alterações (o pacote não usa queue por omissão)
