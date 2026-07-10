<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TipoDocumento;
use App\Models\User;

final class TipoDocumentoPolicy
{
    public function viewAny(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('tipos-documento.ver');
    }

    public function view(User $utilizador, TipoDocumento $tipoDocumento): bool
    {
        return $utilizador->hasPermissionTo('tipos-documento.ver');
    }

    public function create(User $utilizador): bool
    {
        return $utilizador->hasPermissionTo('tipos-documento.criar');
    }

    public function update(User $utilizador, TipoDocumento $tipoDocumento): bool
    {
        return $utilizador->hasPermissionTo('tipos-documento.actualizar');
    }

    public function delete(User $utilizador, TipoDocumento $tipoDocumento): bool
    {
        return $utilizador->hasPermissionTo('tipos-documento.eliminar');
    }
}
