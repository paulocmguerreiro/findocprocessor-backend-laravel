<?php

declare(strict_types=1);

namespace App\Features\Role\Actualizar;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class ActualizarRoleAction
{
    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Role $role, ActualizarRoleDto $dados): Role
    {
        Gate::authorize('update', $role);

        return DB::transaction(function () use ($role, $dados): Role {
            if ($dados->nome !== null) {
                $role->name = $dados->nome;
                $role->save();
            }

            $role->syncPermissions($dados->permissoes);

            return $role->load('permissions');
        });
    }
}
