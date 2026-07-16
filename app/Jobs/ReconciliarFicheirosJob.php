<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Features\Documento\Transicao\RegraReconciliarLocalizacaoFicheiro;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconciliação ficheiro↔BD (1d, #90): varre `Documento`s presos num estado
 * transitório (`AnaliseMalware`/`AnaliseTexto`/`AnaliseOcr`/`AnaliseIaLocal`/
 * `AnaliseCloud`) há mais tempo que
 * `config('pipeline.reconciliacao_limiar_minutos')`. Repõe automaticamente
 * `disco_storage`/`nome_ficheiro_storage` quando o ficheiro é localizado noutro
 * disco conhecido; regista erro estruturado quando não é encontrado em nenhum
 * (sem reposição possível — exige intervenção manual).
 *
 * Agendado via `Schedule::job()` (Tarefa 7) — sem chamadas externas, scan
 * limitado aos documentos presos (nunca a tabela completa).
 */
final class ReconciliarFicheirosJob implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @throws \Throwable
     */
    public function handle(RegraReconciliarLocalizacaoFicheiro $regra): void
    {
        $presos = Documento::query()->wherePresos(
            [
                EstadoDocumento::AnaliseMalware,
                EstadoDocumento::AnaliseTexto,
                EstadoDocumento::AnaliseOcr,
                EstadoDocumento::AnaliseIaLocal,
                EstadoDocumento::AnaliseCloud,
            ],
            config()->integer('pipeline.reconciliacao_limiar_minutos'),
        )->cursor();

        foreach ($presos as $documento) {
            $resultadoReconciliacao = $regra->handle($documento);

            if ($resultadoReconciliacao->coerente) {
                continue;
            }

            if ($resultadoReconciliacao->encontrado) {
                DB::transaction(function () use ($documento, $resultadoReconciliacao): void {
                    $documento->update([
                        'disco_storage' => $resultadoReconciliacao->disco,
                        'nome_ficheiro_storage' => $resultadoReconciliacao->nome,
                    ]);
                });

                continue;
            }

            Log::error('ReconciliarFicheirosJob: ficheiro não encontrado em nenhum disco conhecido.', [
                'id_documento' => $documento->id,
                'disco_esperado' => $documento->disco_storage,
                'nome_esperado' => $documento->nome_ficheiro_storage,
            ]);
        }
    }
}
