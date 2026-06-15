<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Actualizar;

use App\Models\CategoriaDocumento;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ActualizarCategoriaAction
{
    /**
     * @throws ModelNotFoundException
     */
    public function handle(CategoriaDocumento|string $idCategoria, ActualizarCategoriaDto $dados): CategoriaDocumento
    {
        /** @var CategoriaDocumento $categoria */
        $categoria = is_string($idCategoria)
            ? CategoriaDocumento::findOrFail($idCategoria)
            : $idCategoria;

        $campos = array_filter([
            'nome' => $dados->nome,
            'slug' => $dados->slug,
            'tipo_movimento' => $dados->tipo_movimento,
        ], fn (mixed $valor): bool => $valor !== null);

        $categoria->fill($campos)->save();

        $categoria->refresh();

        return $categoria;
    }
}
