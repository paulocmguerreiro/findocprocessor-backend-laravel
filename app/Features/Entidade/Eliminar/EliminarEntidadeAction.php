<?php

declare(strict_types=1);

namespace App\Features\Entidade\Eliminar;

use App\Models\Entidade;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class EliminarEntidadeAction
{
    /**
     * @throws ModelNotFoundException<Entidade>
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Entidade|string $idEntidade): void
    {
        /** @var Entidade $entidade */
        $entidade = is_string($idEntidade)
            ? Entidade::findOrFail($idEntidade)
            : $idEntidade;

        Gate::authorize('delete', $entidade);

        DB::transaction(fn (): ?bool => $entidade->delete());
    }
}
