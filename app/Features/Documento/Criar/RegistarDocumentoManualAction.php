<?php

declare(strict_types=1);

namespace App\Features\Documento\Criar;

use App\Events\DocumentoMarcadoErro;
use App\Events\DocumentoMarcadoPerigoso;
use App\Events\DocumentoProcessado;
use App\Features\Documento\RecepcaoUpload\DocumentoDuplicadoException;
use App\Features\Documento\Transicao\RegraNomearProcessado;
use App\Infrastructure\Malware\AnalisadorMalware;
use App\Infrastructure\Malware\FalhaAnaliseMalwareException;
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
 * Corre o mesmo scan de malware do pipeline automático (`AnalisadorMalware`)
 * antes de gravar — limpo/não configurado vai para `Processado` (disco
 * `processado`, comportamento inalterado); infectado vai para `Perigoso`
 * (disco `perigoso`, motivo = assinatura); falha do scan vai para `Erro`
 * (disco `erro`, motivo = razão da falha). O `Documento` **é sempre criado**
 * — nunca rejeitado sem registo (RN-04) — apenas o `status`/disco/evento
 * variam conforme o resultado do scan.
 */
final readonly class RegistarDocumentoManualAction
{
    private const string DISCO_PROCESSADO = 'processado';

    private const string DISCO_PERIGOSO = 'perigoso';

    private const string DISCO_ERRO = 'erro';

    public function __construct(
        private RegraNomearProcessado $nomear,
        private CacheServico $cache,
        private AnalisadorMalware $analisador,
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

        /** @var array{estado: EstadoDocumento, disco: string, motivo: string} $veredicto */
        $veredicto = $this->avaliarScan((string) $dados->ficheiro->getRealPath());

        $caminho = $dados->ficheiro->storeAs('', $nomeStorage, $veredicto['disco']);

        if ($caminho === false) {
            throw new RuntimeException("Falha ao guardar o ficheiro no disco {$veredicto['disco']}.");
        }

        Log::info('documento.registar_manual.inicio', ['id_utilizador' => Auth::id()]);

        try {
            $documento = DB::transaction(function () use ($dados, $hash, $nomeStorage, $veredicto): Documento {
                $documento = Documento::create([
                    'status' => $veredicto['estado'],
                    'id_responsavel' => Auth::id(),
                    'id_fornecedor' => $dados->idFornecedor,
                    'id_cliente' => $dados->idCliente,
                    'id_categoria' => $dados->idCategoria,
                    'valor' => $dados->valor,
                    'data_documento' => $dados->dataDocumento,
                    'nome_ficheiro_original' => $dados->ficheiro->getClientOriginalName(),
                    'disco_storage' => $veredicto['disco'],
                    'nome_ficheiro_storage' => $nomeStorage,
                    'hash_sha256' => $hash,
                ]);

                $documento->historico()->create([
                    'estado' => $veredicto['estado'],
                    'motivo' => $veredicto['motivo'],
                    'id_utilizador' => Auth::id(),
                ]);

                $this->cache->invalidarCache(TagCache::Documentos);

                match ($veredicto['estado']) {
                    EstadoDocumento::Perigoso => DocumentoMarcadoPerigoso::dispatch($documento, $veredicto['motivo']),
                    EstadoDocumento::Erro => DocumentoMarcadoErro::dispatch($documento, $veredicto['motivo']),
                    default => DocumentoProcessado::dispatch($documento),
                };

                return $documento;
            });
        } catch (\Throwable $erro) {
            Storage::disk($veredicto['disco'])->delete($nomeStorage);

            throw $erro;
        }

        Log::info('documento.registar_manual.fim', ['id_utilizador' => Auth::id(), 'id_documento' => $documento->id]);

        return $documento;
    }

    /**
     * @return array{estado: EstadoDocumento, disco: string, motivo: string}
     */
    private function avaliarScan(string $caminhoAbsoluto): array
    {
        try {
            $resultado = $this->analisador->analisar($caminhoAbsoluto);
        } catch (FalhaAnaliseMalwareException $erro) {
            return ['estado' => EstadoDocumento::Erro, 'disco' => self::DISCO_ERRO, 'motivo' => $erro->getMessage()];
        }

        if ($resultado->estaInfectado()) {
            return [
                'estado' => EstadoDocumento::Perigoso,
                'disco' => self::DISCO_PERIGOSO,
                'motivo' => $resultado->assinatura() ?? 'assinatura desconhecida',
            ];
        }

        return ['estado' => EstadoDocumento::Processado, 'disco' => self::DISCO_PROCESSADO, 'motivo' => 'registo manual'];
    }
}
