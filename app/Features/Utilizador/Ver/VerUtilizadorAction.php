<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Ver;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class VerUtilizadorAction
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $utilizador): User
    {
        Gate::authorize('view', $utilizador);

        return $utilizador->load('roles');
    }
}
