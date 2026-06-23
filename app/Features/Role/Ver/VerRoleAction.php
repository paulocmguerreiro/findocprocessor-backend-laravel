<?php

declare(strict_types=1);

namespace App\Features\Role\Ver;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class VerRoleAction
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Role $role): Role
    {
        Gate::authorize('view', $role);

        return $role->load('permissions');
    }
}
