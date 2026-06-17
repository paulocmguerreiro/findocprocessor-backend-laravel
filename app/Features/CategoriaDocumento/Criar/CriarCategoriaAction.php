<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Criar;

use App\Models\CategoriaDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class CriarCategoriaAction
{
    /**
     * @throws AuthorizationException
     */
    public function handle(CriarCategoriaDto $dados): CategoriaDocumento
    {
        Gate::authorize('create', CategoriaDocumento::class);

        return CategoriaDocumento::create([
            'nome' => $dados->nome,
            'slug' => $dados->slug,
            'tipo_movimento' => $dados->tipoMovimento,
        ]);
    }
}
