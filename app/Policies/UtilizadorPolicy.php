<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UtilizadorPolicy
{
    public function atribuirRole(User $utilizador, User $alvo): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.atribuir-role');
    }

    public function viewAny(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.ver');
    }

    public function view(User $utilizador, User $alvo): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.ver')
            || $utilizador->id === $alvo->id;
    }

    public function create(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.criar');
    }

    public function update(User $utilizador, User $alvo): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.actualizar');
    }

    public function delete(User $utilizador, User $alvo): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.eliminar');
    }

    public function restore(User $utilizador, User $alvo): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.eliminar');
    }

    public function anonimizar(User $utilizador, User $alvo): bool
    {
        return $utilizador->hasPermissionTo('utilizadores.anonimizar');
    }
}
