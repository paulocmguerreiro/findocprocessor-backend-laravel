<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Eliminar;

use App\Models\CategoriaDocumento;

final class EliminarCategoriaAction
{
    public function handle(string $idCategoria): void
    {
        $categoria = CategoriaDocumento::findOrFail($idCategoria);
        $categoria->delete();
    }
}
