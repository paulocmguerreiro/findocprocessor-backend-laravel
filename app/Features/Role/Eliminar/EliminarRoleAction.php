<?php

declare(strict_types=1);

namespace App\Features\Role\Eliminar;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class EliminarRoleAction
{
    private const array ROLES_SISTEMA = ['admin', 'utilizador'];

    /**
     * @throws AuthorizationException
     * @throws \DomainException
     * @throws \Throwable
     */
    public function handle(Role $role): void
    {
        Gate::authorize('delete', $role);

        if (in_array($role->name, self::ROLES_SISTEMA, true)) {
            throw new \DomainException('Não é possível eliminar um role de sistema.');
        }

        DB::transaction(function () use ($role): void {
            $role->delete();
        });
    }
}
