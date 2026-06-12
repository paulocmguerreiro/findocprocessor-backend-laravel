<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Actualizar;

use App\Models\CategoriaDocumento;

final class ActualizarCategoriaAction
{
    public function handle(string $idCategoria, ActualizarCategoriaDto $dados): CategoriaDocumento
    {
        $categoria = CategoriaDocumento::findOrFail($idCategoria);

        $campos = array_filter([
            'nome' => $dados->nome,
            'slug' => $dados->slug,
            'tipo_movimento' => $dados->tipo_movimento,
        ], fn (mixed $valor): bool => $valor !== null);

        $categoria->fill($campos)->save();

        return $categoria->fresh() ?? $categoria;
    }
}
