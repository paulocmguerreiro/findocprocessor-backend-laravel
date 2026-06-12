<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Listar;

use App\Models\CategoriaDocumento;
use Illuminate\Database\Eloquent\Collection;

final class ListarCategoriasAction
{
    /** @return Collection<int, CategoriaDocumento> */
    public function handle(): Collection
    {
        return CategoriaDocumento::all();
    }
}
