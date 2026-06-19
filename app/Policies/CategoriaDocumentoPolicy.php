<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CategoriaDocumento;
use App\Models\User;

final class CategoriaDocumentoPolicy
{
    public function viewAny(User $utilizador): bool
    {
        return true;
    }

    public function view(User $utilizador, CategoriaDocumento $categoriaDocumento): bool
    {
        return true;
    }

    public function create(User $utilizador): bool
    {
        return true;
    }

    public function update(User $utilizador, CategoriaDocumento $categoriaDocumento): bool
    {
        return true;
    }

    public function delete(User $utilizador, CategoriaDocumento $categoriaDocumento): bool
    {
        return true;
    }
}
