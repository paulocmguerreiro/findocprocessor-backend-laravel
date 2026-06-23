<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

final class RolePolicy
{
    public function viewAny(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('roles.ver');
    }

    public function view(User $utilizador, Role $role): bool
    {
        return $utilizador->hasPermissionTo('roles.ver');
    }

    public function create(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('roles.criar');
    }

    public function update(User $utilizador, Role $role): bool
    {
        return $utilizador->hasPermissionTo('roles.actualizar');
    }

    public function delete(User $utilizador, Role $role): bool
    {
        return $utilizador->hasPermissionTo('roles.eliminar');
    }
}
