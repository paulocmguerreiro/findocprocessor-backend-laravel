<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Eliminar;

use App\Models\CategoriaDocumento;

final class EliminarCategoriaAction
{
    public function handle(CategoriaDocumento|string $idCategoria): void
    {
        $categoria = is_string($idCategoria)
            ? CategoriaDocumento::findOrFail($idCategoria)
            : $idCategoria;

        $categoria->delete();
    }
}
