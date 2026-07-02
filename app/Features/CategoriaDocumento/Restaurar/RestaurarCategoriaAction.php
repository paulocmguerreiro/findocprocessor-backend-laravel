<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Restaurar;

use App\Models\CategoriaDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class RestaurarCategoriaAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<CategoriaDocumento>
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(CategoriaDocumento|string $idCategoria): CategoriaDocumento
    {
        /** @var CategoriaDocumento $categoria */
        $categoria = is_string($idCategoria)
            ? CategoriaDocumento::withTrashed()->findOrFail($idCategoria)
            : $idCategoria;

        Gate::authorize('restore', $categoria);

        DB::transaction(function () use ($categoria): void {
            $categoria->restore();
            $this->cache->invalidarCache(TagCache::CategoriasDocumento);
        });

        return $categoria;
    }
}
