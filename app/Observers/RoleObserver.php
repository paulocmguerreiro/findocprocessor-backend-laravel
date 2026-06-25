<?php

declare(strict_types=1);

namespace App\Observers;

use Spatie\Permission\Models\Role;

/**
 * Audita o modelo de terceiro Spatie\Permission\Models\Role.
 *
 * Como Role não é um modelo nosso, não pode usar o trait LogsActivity;
 * o registo de actividade é feito manualmente via helper activity(),
 * dentro da DB::transaction() das Actions (atomicidade garantida).
 */
final class RoleObserver
{
    public function created(Role $role): void
    {
        activity()
            ->performedOn($role)
            ->event('created')
            ->log('created');
    }

    public function updated(Role $role): void
    {
        // O evento updated do Eloquent só dispara quando há alterações reais,
        // pelo que não é necessário um guard a getDirty().
        activity()
            ->performedOn($role)
            ->event('updated')
            ->withProperties([
                'old' => $role->getOriginal(),
                'attributes' => $role->getAttributes(),
            ])
            ->log('updated');
    }

    public function deleted(Role $role): void
    {
        activity()
            ->performedOn($role)
            ->event('deleted')
            ->log('deleted');
    }
}
