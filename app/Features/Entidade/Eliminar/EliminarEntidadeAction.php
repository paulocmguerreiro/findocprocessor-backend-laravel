<?php

declare(strict_types=1);

namespace App\Features\Entidade\Eliminar;

use App\Models\Entidade;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class EliminarEntidadeAction
{
    public function __construct(private CacheServico $cache) {}

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

        Log::info('entidade.eliminar.inicio', ['id_utilizador' => Auth::id()]);

        DB::transaction(function () use ($entidade): void {
            try {
                $entidade->forceDelete();
            } catch (QueryException) {
                $entidade->delete();
            }

            $this->cache->invalidarCache(TagCache::Entidades);
        });

        Log::info('entidade.eliminar.fim', ['id_utilizador' => Auth::id()]);
    }
}
