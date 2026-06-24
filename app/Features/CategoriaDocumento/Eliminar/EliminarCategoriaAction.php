<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Eliminar;

use App\Models\CategoriaDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class EliminarCategoriaAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<CategoriaDocumento>
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(CategoriaDocumento|string $idCategoria): void
    {
        /** @var CategoriaDocumento $categoria */
        $categoria = is_string($idCategoria)
            ? CategoriaDocumento::findOrFail($idCategoria)
            : $idCategoria;

        Gate::authorize('delete', $categoria);

        DB::transaction(function () use ($categoria): void {
            $categoria->delete();
            $this->cache->invalidarCache(TagCache::CategoriasDocumento);
        });
    }
}
