<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Entidade;
use App\Models\User;

final class EntidadePolicy
{
    public function viewAny(?User $utilizador): bool
    {
        return true;
    }

    public function view(?User $utilizador, Entidade $entidade): bool
    {
        return true;
    }

    public function create(?User $utilizador): bool
    {
        return true;
    }

    public function update(?User $utilizador, Entidade $entidade): bool
    {
        return true;
    }

    public function delete(?User $utilizador, Entidade $entidade): bool
    {
        return true;
    }
}
