<?php

declare(strict_types=1);

namespace App\Features\Entidade\Ver;

use App\Models\Entidade;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

final class VerEntidadeAction
{
    /**
     * @throws ModelNotFoundException<Entidade>
     * @throws AuthorizationException
     */
    public function handle(Entidade|string $idEntidade): Entidade
    {
        /** @var Entidade $entidade */
        $entidade = is_string($idEntidade)
            ? Entidade::findOrFail($idEntidade)
            : $idEntidade;

        Gate::authorize('view', $entidade);

        return $entidade;
    }
}
