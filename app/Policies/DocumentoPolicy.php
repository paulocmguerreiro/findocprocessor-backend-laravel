<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Documento;
use App\Models\User;

final class DocumentoPolicy
{
    public function viewAny(User $utilizador): bool
    {
        return true;
    }

    public function view(User $utilizador, Documento $documento): bool
    {
        return true;
    }

    public function create(User $utilizador): bool
    {
        return true;
    }

    public function update(User $utilizador, Documento $documento): bool
    {
        return true;
    }

    public function delete(User $utilizador, Documento $documento): bool
    {
        return true;
    }
}
