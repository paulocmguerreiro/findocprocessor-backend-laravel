<?php

declare(strict_types=1);

namespace App\Features\Utilizador\AtribuirRole;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AtribuirRoleAction
{
    /**
     * @throws AuthorizationException
     * @throws \DomainException
     * @throws \Throwable
     */
    public function handle(User $utilizador, string $nomeRole): User
    {
        Gate::authorize('atribuirRole', $utilizador);

        if (auth()->id() === $utilizador->id) {
            throw new \DomainException('Não é possível alterar o próprio role.');
        }

        DB::transaction(function () use ($utilizador, $nomeRole): void {
            $utilizador->syncRoles([$nomeRole]);
        });

        return $utilizador->load('roles');
    }
}
