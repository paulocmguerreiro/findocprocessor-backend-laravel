<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Ver;

use App\Models\CategoriaDocumento;

final class VerCategoriaAction
{
    public function handle(string $idCategoria): CategoriaDocumento
    {
        return CategoriaDocumento::findOrFail($idCategoria);
    }
}
