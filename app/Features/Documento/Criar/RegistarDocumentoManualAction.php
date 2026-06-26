<?php

declare(strict_types=1);

namespace App\Features\Documento\Criar;

use App\Events\DocumentoProcessado;
use App\Features\Documento\RecepcaoUpload\DocumentoDuplicadoException;
use App\Features\Documento\Transicao\RegraNomearProcessado;
use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
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
 * Registo manual: o utilizador envia o ficheiro já tratado + os dados de domínio.
 * Vai directo a `Processado` — a Action calcula o hash, gera o nome canónico
 * (`RegraNomearProcessado`), escreve no disco `processado`, grava uma única
 * `EtapaDocumento` e emite `DocumentoProcessado`.
 */
final readonly class RegistarDocumentoManualAction
{
    private const string DISCO_PROCESSADO = 'processado';

    public function __construct(
        private RegraNomearProcessado $nomear,
        private CacheServico $cache,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws DocumentoDuplicadoException
     * @throws \Throwable
     */
    public function handle(RegistarDocumentoManualDto $dados): Documento
    {
        Gate::authorize('create', Documento::class);

        $hash = (string) hash_file('sha256', (string) $dados->ficheiro->getRealPath());

        if (Documento::query()->where('hash_sha256', $hash)->exists()) {
            throw DocumentoDuplicadoException::paraHash($hash);
        }

        $fornecedor = Entidade::findOrFail($dados->idFornecedor);
        $categoria = CategoriaDocumento::findOrFail($dados->idCategoria);

        $nomeStorage = $this->nomear->handle(
            $dados->dataDocumento,
            $fornecedor->nome,
            $categoria->nome,
            $dados->ficheiro->getClientOriginalName(),
        );

        $caminho = $dados->ficheiro->storeAs('', $nomeStorage, self::DISCO_PROCESSADO);

        if ($caminho === false) {
            throw new RuntimeException('Falha ao guardar o ficheiro no disco processado.');
        }

        Log::info('documento.registar_manual.inicio', ['id_utilizador' => Auth::id()]);

        try {
            $documento = DB::transaction(function () use ($dados, $hash, $nomeStorage): Documento {
                $documento = Documento::create([
                    'status' => EstadoDocumento::Processado,
                    'id_responsavel' => Auth::id(),
                    'id_fornecedor' => $dados->idFornecedor,
                    'id_cliente' => $dados->idCliente,
                    'id_categoria' => $dados->idCategoria,
                    'valor' => $dados->valor,
                    'data_documento' => $dados->dataDocumento,
                    'nome_ficheiro_original' => $dados->ficheiro->getClientOriginalName(),
                    'disco_storage' => self::DISCO_PROCESSADO,
                    'nome_ficheiro_storage' => $nomeStorage,
                    'hash_sha256' => $hash,
                ]);

                $documento->historico()->create([
                    'estado' => EstadoDocumento::Processado,
                    'motivo' => 'registo manual',
                    'id_utilizador' => Auth::id(),
                ]);

                $this->cache->invalidarCache(TagCache::Documentos);

                DocumentoProcessado::dispatch($documento);

                return $documento;
            });
        } catch (\Throwable $erro) {
            Storage::disk(self::DISCO_PROCESSADO)->delete($nomeStorage);

            throw $erro;
        }

        Log::info('documento.registar_manual.fim', ['id_utilizador' => Auth::id(), 'id_documento' => $documento->id]);

        return $documento;
    }
}
