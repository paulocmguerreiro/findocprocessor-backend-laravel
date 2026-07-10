<?php

declare(strict_types=1);

namespace App\Features\TipoDocumento\Eliminar;

use App\Models\TipoDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class EliminarTipoDocumentoAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<TipoDocumento>
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(TipoDocumento|string $idTipoDocumento): void
    {
        /** @var TipoDocumento $tipoDocumento */
        $tipoDocumento = is_string($idTipoDocumento)
            ? TipoDocumento::findOrFail($idTipoDocumento)
            : $idTipoDocumento;

        Gate::authorize('delete', $tipoDocumento);

        Log::info('tipo_documento.eliminar.inicio', ['id_utilizador' => Auth::id()]);

        DB::transaction(function () use ($tipoDocumento): void {
            $tipoDocumento->delete();
            $this->cache->invalidarCache(TagCache::TiposDocumento);
        });

        Log::info('tipo_documento.eliminar.fim', ['id_utilizador' => Auth::id()]);
    }
}
