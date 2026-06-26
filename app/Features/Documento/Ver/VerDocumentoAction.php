<?php

declare(strict_types=1);

namespace App\Features\Documento\Ver;

use App\Models\Documento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

final readonly class VerDocumentoAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<Documento>
     * @throws AuthorizationException
     */
    public function handle(Documento|string $idDocumento): Documento
    {
        /** @var Documento $documento */
        $documento = is_string($idDocumento)
            ? Documento::findOrFail($idDocumento)
            : $idDocumento;

        Gate::authorize('view', $documento);

        $documento->load('historico');

        $chave = $this->cache->criarChave(TagCache::Documentos, TagOperacao::Ver, ['id' => $documento->id]);

        /** @var Documento $resultado */
        $resultado = $this->cache->lembrar(
            TagCache::Documentos,
            $chave,
            TtlCache::Media,
            fn (): Documento => $documento,
        );

        return $resultado;
    }
}
