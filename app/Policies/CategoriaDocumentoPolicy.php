<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CategoriaDocumento;
use App\Models\User;

final class CategoriaDocumentoPolicy
{
    public function viewAny(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('categorias-documento.ver');
    }

    public function view(User $utilizador, CategoriaDocumento $categoriaDocumento): bool
    {
        return $utilizador->hasPermissionTo('categorias-documento.ver');
    }

    public function create(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('categorias-documento.criar');
    }

    public function update(User $utilizador, CategoriaDocumento $categoriaDocumento): bool
    {
        return $utilizador->hasPermissionTo('categorias-documento.actualizar');
    }

    public function delete(User $utilizador, CategoriaDocumento $categoriaDocumento): bool
    {
        return $utilizador->hasPermissionTo('categorias-documento.eliminar');
    }
}
