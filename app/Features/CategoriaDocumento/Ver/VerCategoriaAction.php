<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Ver;

use App\Models\CategoriaDocumento;

final class VerCategoriaAction
{
    public function handle(CategoriaDocumento|string $idCategoria): CategoriaDocumento
    {
        return is_string($idCategoria)
            ? CategoriaDocumento::findOrFail($idCategoria)
            : $idCategoria;
    }
}
