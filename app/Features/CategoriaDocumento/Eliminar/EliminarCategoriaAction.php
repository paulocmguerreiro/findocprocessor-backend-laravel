<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Eliminar;

use App\Models\CategoriaDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

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

        Log::info('categoria.eliminar.inicio', ['id_utilizador' => Auth::id()]);

        DB::transaction(function () use ($categoria): void {
            try {
                $categoria->forceDelete();
            } catch (QueryException) {
                // forceDelete() deixa forceDeleting=true ao lançar; recarregar da BD garante soft delete real.
                CategoriaDocumento::withTrashed()->whereKey($categoria->getKey())->firstOrFail()->delete();
            }
            $this->cache->invalidarCache(TagCache::CategoriasDocumento);
        });

        Log::info('categoria.eliminar.fim', ['id_utilizador' => Auth::id()]);
    }
}
