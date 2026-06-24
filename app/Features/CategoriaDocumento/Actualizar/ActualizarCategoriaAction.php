<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Actualizar;

use App\Models\CategoriaDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class ActualizarCategoriaAction
{
    public function __construct(private CacheServico $cache) {}

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

        Log::info('categoria.actualizar.inicio', ['id_utilizador' => Auth::id()]);

        $categoria = DB::transaction(function () use ($categoria, $dados): CategoriaDocumento {
            $categoria->fill([
                'nome' => $dados->nome,
                'slug' => $dados->slug,
                'tipo_movimento' => $dados->tipoMovimento,
            ])->save();

            $categoria->refresh();

            $this->cache->invalidarCache(TagCache::CategoriasDocumento);

            return $categoria;
        });

        Log::info('categoria.actualizar.fim', ['id_utilizador' => Auth::id(), 'id_categoria' => $categoria->id]);

        return $categoria;
    }
}
