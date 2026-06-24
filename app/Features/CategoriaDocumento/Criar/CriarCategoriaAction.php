<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Criar;

use App\Models\CategoriaDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class CriarCategoriaAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(CriarCategoriaDto $dados): CategoriaDocumento
    {
        Gate::authorize('create', CategoriaDocumento::class);

        Log::info('categoria.criar.inicio', ['id_utilizador' => Auth::id()]);

        $categoria = DB::transaction(function () use ($dados): CategoriaDocumento {
            $categoria = CategoriaDocumento::create([
                'nome' => $dados->nome,
                'slug' => $dados->slug,
                'tipo_movimento' => $dados->tipoMovimento,
            ]);

            $this->cache->invalidarCache(TagCache::CategoriasDocumento);

            return $categoria;
        });

        Log::info('categoria.criar.fim', ['id_utilizador' => Auth::id(), 'id_categoria' => $categoria->id]);

        return $categoria;
    }
}
