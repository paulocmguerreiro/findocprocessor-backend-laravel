<?php

declare(strict_types=1);

namespace App\Features\Documento\Eliminar;

use App\Models\Documento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Elimina o Documento (em qualquer estado) e o respectivo ficheiro. O histórico
 * (`EtapaDocumento`) é removido por `cascadeOnDelete()`. O ficheiro é apagado
 * **após** o commit — se o registo não for eliminado, o ficheiro fica intacto.
 */
final readonly class EliminarDocumentoAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Documento $documento): void
    {
        Gate::authorize('delete', $documento);

        $disco = $documento->disco_storage;
        $nomeFicheiro = $documento->nome_ficheiro_storage;

        DB::transaction(function () use ($documento): void {
            $documento->delete();

            $this->cache->invalidarCache(TagCache::Documentos);
        });

        Storage::disk($disco)->delete($nomeFicheiro);
    }
}
