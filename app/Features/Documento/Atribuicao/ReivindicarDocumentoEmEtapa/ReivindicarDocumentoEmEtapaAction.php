<?php

declare(strict_types=1);

namespace App\Features\Documento\Atribuicao\ReivindicarDocumentoEmEtapa;

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reivindicação por *lease* de um `Documento` numa etapa de análise
 * (`AnaliseTexto`/`AnaliseOcr`/`AnaliseIaLocal`/`AnaliseCloud`). Ao contrário de
 * `ReivindicarDocumentoPendenteAction` — que colapsa a reivindicação na triagem
 * porque o estado muda logo — estas etapas **não** mudam de estado ao serem
 * reclamadas, pelo que a exclusão mútua entre workers assenta num lease com TTL
 * gravado em `extracoes_documento.extracao_reclamada_em` (RF-08, RNF-01).
 *
 * Dentro de uma só `DB::transaction()`: selecciona sob `lockForUpdate()` o
 * documento mais antigo no `$estado` cujo lease é **nulo ou expirado**
 * (`extracao_reclamada_em < now - config('extracao.ttl_lease')`), grava
 * `extracao_reclamada_em = now()` (criando a linha `extracoes_documento` se ainda
 * não existir) e devolve-o; `null` se não houver candidato. Se um segundo worker
 * ficar à espera do lock, ao obtê-lo relê o estado já commitado e o lease fresco
 * exclui o documento — cada worker reclama um documento distinto.
 *
 * Acção de sistema/pipeline — sem `Gate::authorize()`, tal como
 * `ReivindicarDocumentoPendenteAction`/`TriarDocumentoPendenteAction`.
 */
final readonly class ReivindicarDocumentoEmEtapaAction
{
    /**
     * @throws \Throwable
     */
    public function handle(EstadoDocumento $estado): ?Documento
    {
        return DB::transaction(function () use ($estado): ?Documento {
            $ttlLeaseSegundos = config()->integer('extracao.ttl_lease');
            $leaseExpiraAntesDe = now()->subSeconds($ttlLeaseSegundos);

            $documento = Documento::query()
                ->whereEstado($estado)
                ->whereDoesntHave('extracao', function (Builder $extracao) use ($leaseExpiraAntesDe): void {
                    $extracao->where('extracao_reclamada_em', '>=', $leaseExpiraAntesDe);
                })
                ->oldest()
                ->lockForUpdate()
                ->first();

            if (! $documento instanceof Documento) {
                return null;
            }

            $documento->extracao()->updateOrCreate([], ['extracao_reclamada_em' => now()]);

            return $documento;
        });
    }
}
