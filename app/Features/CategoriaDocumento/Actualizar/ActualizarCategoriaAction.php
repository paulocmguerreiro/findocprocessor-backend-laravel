<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Actualizar;

use App\Models\CategoriaDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

final class ActualizarCategoriaAction
{
    /**
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function handle(CategoriaDocumento|string $idCategoria, ActualizarCategoriaDto $dados): CategoriaDocumento
    {
        /** @var CategoriaDocumento $categoria */
        $categoria = is_string($idCategoria)
            ? CategoriaDocumento::findOrFail($idCategoria)
            : $idCategoria;

        Gate::authorize('update', $categoria);

        $camposParaActualizar = array_filter([
            'nome' => $dados->nome,
            'slug' => $dados->slug,
            'tipo_movimento' => $dados->tipoMovimento,
        ], fn (mixed $valor): bool => $valor !== null);

        $categoria->fill($camposParaActualizar)->save();

        $categoria->refresh();

        return $categoria;
    }
}
