<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Entidade;
use App\Models\User;

final class EntidadePolicy
{
    public function viewAny(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('entidades.ver');
    }

    public function view(User $utilizador, Entidade $entidade): bool
    {
        return $utilizador->hasPermissionTo('entidades.ver');
    }

    public function create(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('entidades.criar');
    }

    public function update(User $utilizador, Entidade $entidade): bool
    {
        return $utilizador->hasPermissionTo('entidades.actualizar');
    }

    public function delete(User $utilizador, Entidade $entidade): bool
    {
        return $utilizador->hasPermissionTo('entidades.eliminar');
    }

    public function restore(User $utilizador, Entidade $entidade): bool
    {
        return $utilizador->hasPermissionTo('entidades.eliminar');
    }

    public function agrupar(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('entidades.agrupar');
    }
}
