<?php

declare(strict_types=1);

namespace App\Console\Commands\Extracao;

use App\Features\Documento\Processamento\ProcessarAnaliseIaLocal\ProcessarAnaliseIaLocalDocumentoAction;
use App\Models\Documento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

/**
 * Etapa de IA local (RF-07): processa **1 documento por ciclo** em `AnaliseIaLocal`
 * (o modelo local é pesado — M1 8GB).
 */
#[Signature('extracao:run-ia-local')]
#[Description('Processa 1 documento por ciclo em AnaliseIaLocal (modelo de IA local).')]
final class ExecutarIaLocalExtracaoCommand extends EtapaExtracaoCommand
{
    public function __construct(private readonly ProcessarAnaliseIaLocalDocumentoAction $processar)
    {
        parent::__construct();
    }

    protected function processarProximo(): ?Documento
    {
        return $this->processar->handle();
    }

    protected function loteMaximo(): int
    {
        return 1;
    }
}
