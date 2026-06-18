<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Actualizar;

use App\Models\CategoriaDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ActualizarCategoriaAction
{
    /**
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(CategoriaDocumento|string $idCategoria, ActualizarCategoriaDto $dados): CategoriaDocumento
    {
        /** @var CategoriaDocumento $categoria */
        $categoria = is_string($idCategoria)
            ? CategoriaDocumento::findOrFail($idCategoria)
            : $idCategoria;

        Gate::authorize('update', $categoria);

        return DB::transaction(function () use ($categoria, $dados): CategoriaDocumento {
            $categoria->fill([
                'nome' => $dados->nome,
                'slug' => $dados->slug,
                'tipo_movimento' => $dados->tipoMovimento,
            ])->save();

            $categoria->refresh();

            return $categoria;
        });
    }
}
