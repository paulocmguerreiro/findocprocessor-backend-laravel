<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Eliminar;

use App\Models\CategoriaDocumento;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EliminarCategoriaAction
{
    /**
     * @throws ModelNotFoundException<CategoriaDocumento>
     */
    public function handle(CategoriaDocumento|string $idCategoria): void
    {
        /** @var CategoriaDocumento $categoria */
        $categoria = is_string($idCategoria)
            ? CategoriaDocumento::findOrFail($idCategoria)
            : $idCategoria;

        $categoria->delete();
    }
}
