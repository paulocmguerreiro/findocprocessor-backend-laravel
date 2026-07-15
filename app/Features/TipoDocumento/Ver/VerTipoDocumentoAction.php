<?php

declare(strict_types=1);

namespace App\Features\TipoDocumento\Ver;

use App\Models\TipoDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

final readonly class VerTipoDocumentoAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<TipoDocumento>
     * @throws AuthorizationException
     */
    public function handle(TipoDocumento|string $idTipoDocumento): TipoDocumento
    {
        /** @var TipoDocumento $tipoDocumento */
        $tipoDocumento = is_string($idTipoDocumento)
            ? TipoDocumento::findOrFail($idTipoDocumento)
            : $idTipoDocumento;

        Gate::authorize('view', $tipoDocumento);

        $tipoDocumento->loadMissing('categoria');

        $chave = $this->cache->criarChave(TagCache::TiposDocumento, TagOperacao::Ver, ['id' => $tipoDocumento->id]);

        /** @var TipoDocumento $tipoDocumentoEmCache */
        $tipoDocumentoEmCache = $this->cache->lembrar(
            TagCache::TiposDocumento,
            $chave,
            TtlCache::Media,
            fn (): TipoDocumento => $tipoDocumento,
        );

        return $tipoDocumentoEmCache;
    }
}
