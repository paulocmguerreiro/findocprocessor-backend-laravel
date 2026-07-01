<?php

declare(strict_types=1);

namespace App\Features\Entidade\Restaurar;

use App\Models\Entidade;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class RestaurarEntidadeAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<Entidade>
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Entidade|string $idEntidade): Entidade
    {
        /** @var Entidade $entidade */
        $entidade = is_string($idEntidade)
            ? Entidade::withTrashed()->findOrFail($idEntidade)
            : $idEntidade;

        Gate::authorize('restore', $entidade);

        DB::transaction(function () use ($entidade): void {
            $entidade->restore();
            $this->cache->invalidarCache(TagCache::Entidades);
        });

        return $entidade;
    }
}
