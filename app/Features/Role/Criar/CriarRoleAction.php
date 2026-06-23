<?php

declare(strict_types=1);

namespace App\Features\Role\Criar;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class CriarRoleAction
{
    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(CriarRoleDto $dados): Role
    {
        Gate::authorize('create', Role::class);

        return DB::transaction(function () use ($dados): Role {
            $role = Role::create([
                'name' => $dados->nome,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($dados->permissoes);

            return $role->load('permissions');
        });
    }
}
