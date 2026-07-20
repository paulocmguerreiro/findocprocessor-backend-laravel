<?php

declare(strict_types=1);

namespace App\Features\Documento\Atribuicao;

use App\Features\Documento\Processamento\ProcessarAnaliseMalwareDocumentoAction;
use App\Models\Documento;
use Illuminate\Support\Facades\DB;

/**
 * Reivindicação de um `Documento` `Pendente` por um worker do pipeline: bloqueia
 * (`lockForUpdate()`) a linha dentro da própria transacção, evitando que dois
 * workers reivindiquem o mesmo documento em simultâneo. `ProcessarAnaliseMalwareDocumentoAction`
 * corre o scan de malware e transiciona o Documento (via `RegraTransicaoEstado`,
 * último nível de validação) ainda dentro desta transacção (RN-01, issue #91).
 *
 * Componente reutilizável, sem Job concreto nesta issue (#90) — invocado pela
 * issue futura do orquestrador. Acção de sistema/pipeline: sem `Gate::authorize()`,
 * mesmo padrão de `ProcessarAnaliseMalwareDocumentoAction`.
 */
final readonly class ReivindicarDocumentoPendenteAction
{
    public function __construct(private ProcessarAnaliseMalwareDocumentoAction $processarAnaliseMalware) {}

    /**
     * @throws \Throwable
     */
    public function handle(): ?Documento
    {
        return DB::transaction(function (): ?Documento {
            $documento = Documento::query()->wherePendente()->lockForUpdate()->first();

            if (! $documento instanceof Documento) {
                return null;
            }

            return $this->processarAnaliseMalware->handle($documento);
        });
    }
}
