<?php

declare(strict_types=1);

namespace App\Console\Commands\Extracao;

use App\Features\Documento\Processamento\ProcessarAnaliseCloud\ProcessarAnaliseCloudDocumentoAction;
use App\Models\Documento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

/**
 * Etapa de IA cloud (RF-07): processa em lote os documentos em `AnaliseCloud` (a
 * última camada de análise). Agendado com menor frequência (everyFiveMinutes).
 */
#[Signature('extracao:run-ia-cloud')]
#[Description('Processa em lote os documentos em AnaliseCloud (modelo de IA cloud).')]
final class ExecutarIaCloudExtracaoCommand extends ComandoExtracao
{
    public function __construct(private readonly ProcessarAnaliseCloudDocumentoAction $processar)
    {
        parent::__construct();
    }

    protected function processarProximo(): ?Documento
    {
        return $this->processar->handle();
    }

    protected function loteMaximo(): int
    {
        return self::LOTE_PADRAO;
    }
}
