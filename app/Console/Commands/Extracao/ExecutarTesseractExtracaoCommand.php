<?php

declare(strict_types=1);

namespace App\Console\Commands\Extracao;

use App\Features\Documento\Processamento\ProcessarAnaliseOcrDocumentoAction;
use App\Models\Documento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

/**
 * Etapa de OCR (RF-06): processa **1 documento por ciclo** em `AnaliseOcr`
 * (Tesseract é pesado — M1 8GB).
 */
#[Signature('extracao:run-tesseract')]
#[Description('Processa 1 documento por ciclo em AnaliseOcr (OCR via Tesseract).')]
final class ExecutarTesseractExtracaoCommand extends EtapaExtracaoCommand
{
    public function __construct(private readonly ProcessarAnaliseOcrDocumentoAction $processar)
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
