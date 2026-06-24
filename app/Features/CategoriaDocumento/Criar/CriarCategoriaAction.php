<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Criar;

use App\Models\CategoriaDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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

        return DB::transaction(function () use ($dados): CategoriaDocumento {
            $categoria = CategoriaDocumento::create([
                'nome' => $dados->nome,
                'slug' => $dados->slug,
                'tipo_movimento' => $dados->tipoMovimento,
            ]);

            $this->cache->invalidarCache(TagCache::CategoriasDocumento);

            return $categoria;
        });
    }
}
