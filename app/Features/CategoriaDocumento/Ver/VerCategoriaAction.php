<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Ver;

use App\Models\CategoriaDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

final readonly class VerCategoriaAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<CategoriaDocumento>
     * @throws AuthorizationException
     */
    public function handle(CategoriaDocumento|string $idCategoria): CategoriaDocumento
    {
        /** @var CategoriaDocumento $categoria */
        $categoria = is_string($idCategoria)
            ? CategoriaDocumento::findOrFail($idCategoria)
            : $idCategoria;

        Gate::authorize('view', $categoria);

        $chave = $this->cache->criarChave(TagCache::CategoriasDocumento, TagOperacao::Ver, ['id' => $categoria->id]);

        /** @var CategoriaDocumento $resultado */
        $resultado = $this->cache->lembrar(
            TagCache::CategoriasDocumento,
            $chave,
            TtlCache::Media,
            fn (): CategoriaDocumento => $categoria,
        );

        return $resultado;
    }
}
