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
}
