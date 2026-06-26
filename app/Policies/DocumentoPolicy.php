<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Documento;
use App\Models\User;

final class DocumentoPolicy
{
    public function viewAny(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('documentos.ver');
    }

    public function view(User $utilizador, Documento $documento): bool
    {
        return $utilizador->hasPermissionTo('documentos.ver');
    }

    public function create(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('documentos.criar');
    }

    public function update(User $utilizador, Documento $documento): bool
    {
        return $utilizador->hasPermissionTo('documentos.actualizar');
    }

    public function delete(User $utilizador, Documento $documento): bool
    {
        return $utilizador->hasPermissionTo('documentos.eliminar');
    }
}
