<?php

declare(strict_types=1);

namespace App\Features\Documento\RecepcaoUpload;

use App\Models\Documento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Recepção de upload: calcula o `hash_sha256`, escreve o ficheiro no disco
 * `entrada` e cria o Documento em `Pendente`. O ficheiro é escrito antes da
 * transação; se a persistência falhar, é removido (compensação best-effort).
 */
final readonly class ReceberUploadDocumentoAction
{
    private const string DISCO_ENTRADA = 'entrada';

    public function __construct(private CacheServico $cache) {}

    /**
     * @throws AuthorizationException
     * @throws DocumentoDuplicadoException
     * @throws \Throwable
     */
    public function handle(ReceberUploadDocumentoDto $dados): Documento
    {
        Gate::authorize('create', Documento::class);

        $ficheiro = $dados->ficheiro;
        $hash = (string) hash_file('sha256', (string) $ficheiro->getRealPath());

        if (Documento::query()->where('hash_sha256', $hash)->exists()) {
            throw DocumentoDuplicadoException::paraHash($hash);
        }

        $nomeStorage = $ficheiro->hashName();
        $caminho = $ficheiro->storeAs('', $nomeStorage, self::DISCO_ENTRADA);

        if ($caminho === false) {
            throw new RuntimeException('Falha ao guardar o ficheiro no disco de entrada.');
        }

        Log::info('documento.upload.inicio', ['id_utilizador' => Auth::id()]);

        try {
            return DB::transaction(function () use ($dados, $hash, $nomeStorage): Documento {
                $documento = Documento::create([
                    'estado' => EstadoDocumento::Pendente,
                    'id_responsavel' => Auth::id(),
                    'nome_ficheiro_original' => $dados->ficheiro->getClientOriginalName(),
                    'disco_storage' => self::DISCO_ENTRADA,
                    'nome_ficheiro_storage' => $nomeStorage,
                    'hash_sha256' => $hash,
                ]);

                $documento->historico()->create([
                    'estado' => EstadoDocumento::Pendente,
                    'motivo' => 'upload recebido',
                    'id_utilizador' => Auth::id(),
                ]);

                $this->cache->invalidarCache(TagCache::Documentos);

                return $documento;
            });
        } catch (\Throwable $erro) {
            Storage::disk(self::DISCO_ENTRADA)->delete($nomeStorage);

            throw $erro;
        }
    }
}
