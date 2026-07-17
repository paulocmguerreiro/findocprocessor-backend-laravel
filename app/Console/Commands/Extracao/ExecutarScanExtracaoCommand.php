<?php

declare(strict_types=1);

namespace App\Console\Commands\Extracao;

use App\Features\Documento\Atribuicao\Reivindicar\ReivindicarDocumentoPendenteAction;
use App\Models\Documento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

/**
 * Etapa de scan de malware (RF-04): selecciona documentos `Pendente` e reutiliza
 * `ReivindicarDocumentoPendenteAction` (que corre a triagem `Pendente →
 * AnaliseMalware → AnaliseTexto|Perigoso|Erro` atomicamente). Em lote.
 */
#[Signature('extracao:run-scan')]
#[Description('Reclama e tria (scan de malware) os documentos Pendente, em lote.')]
final class ExecutarScanExtracaoCommand extends EtapaExtracaoCommand
{
    public function __construct(private readonly ReivindicarDocumentoPendenteAction $reivindicar)
    {
        parent::__construct();
    }

    protected function processarProximo(): ?Documento
    {
        return $this->reivindicar->handle();
    }

    protected function loteMaximo(): int
    {
        return self::LOTE_PADRAO;
    }
}
