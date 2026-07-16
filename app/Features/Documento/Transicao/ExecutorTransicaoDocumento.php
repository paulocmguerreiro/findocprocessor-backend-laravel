<?php

declare(strict_types=1);

namespace App\Features\Documento\Transicao;

use App\Models\Documento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Enums\EstadoDocumento;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Orquestrador partilhado das transições de estado do Documento (evita duplicar
 * a mecânica entre as Actions). Valida a transição, move o ficheiro, persiste
 * (estado + localização + campos de domínio), grava uma `EtapaDocumento` e
 * dispara o evento — tudo numa só `DB::transaction()`.
 *
 * Atomicidade ficheiro↔BD: o ficheiro é movido **antes** da transação; se a
 * transação (incluindo o commit) falhar, o ficheiro é reposto na origem
 * (compensação best-effort), porque o filesystem não participa no rollback.
 */
final readonly class ExecutorTransicaoDocumento
{
    public function __construct(
        private RegraTransicaoEstado $regraTransicao,
        private RegraMoverFicheiro $regraMover,
        private RegraEliminarExtracaoTerminal $regraEliminarExtracao,
        private CacheServico $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $camposDominio  Campos extra a actualizar (ex.: fornecedor/valor no Processado).
     * @param  (Closure(Documento): object)|null  $evento  Factory do evento de domínio a emitir, ou null.
     *
     * @throws \Throwable
     */
    public function executar(
        Documento $documento,
        EstadoDocumento $novoEstado,
        string $motivo,
        array $camposDominio = [],
        ?string $nomeDestino = null,
        ?Closure $evento = null,
    ): Documento {
        $estadoOrigem = $documento->estado;
        $discoOrigem = $documento->disco_storage;
        $nomeOrigem = $documento->nome_ficheiro_storage;

        $this->regraTransicao->handle($estadoOrigem, $novoEstado);

        $destino = $this->regraMover->handle($discoOrigem, $nomeOrigem, $novoEstado, $nomeDestino);

        try {
            return DB::transaction(function () use ($documento, $novoEstado, $motivo, $camposDominio, $destino, $evento): Documento {
                $documento->update([
                    ...$camposDominio,
                    'estado' => $novoEstado,
                    'disco_storage' => $destino['disco'],
                    'nome_ficheiro_storage' => $destino['nome'],
                ]);

                // RGPD (RN-03/RF-09, #110): ao chegar a um terminal, o scratch space
                // de extracção (incl. PII) é eliminado — condicionante na própria Regra.
                $this->regraEliminarExtracao->handle($documento, $novoEstado);

                $documento->historico()->create([
                    'estado' => $novoEstado,
                    'motivo' => $motivo,
                    'id_utilizador' => Auth::id(),
                ]);

                $this->cache->invalidarCache(TagCache::Documentos);

                if ($evento instanceof Closure) {
                    Event::dispatch($evento($documento));
                }

                return $documento;
            });
        } catch (\Throwable $erro) {
            // Compensação: repor o ficheiro na origem.
            $this->regraMover->handle($destino['disco'], $destino['nome'], $estadoOrigem, $nomeOrigem);

            throw $erro;
        }
    }
}
