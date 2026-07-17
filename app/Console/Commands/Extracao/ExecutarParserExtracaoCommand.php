<?php

declare(strict_types=1);

namespace App\Console\Commands\Extracao;

use App\Features\Documento\Processamento\ProcessarAnaliseTexto\ProcessarAnaliseTextoDocumentoAction;
use App\Models\Documento;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

/**
 * Etapa do parser nativo (RF-05): processa em lote os documentos em `AnaliseTexto`
 * — PDF pelo parser (threshold → IA local | OCR), imagem salta directo para OCR.
 */
#[Signature('extracao:run-parser')]
#[Description('Processa em lote os documentos em AnaliseTexto (parser nativo de PDF; imagem salta para OCR).')]
final class ExecutarParserExtracaoCommand extends ComandoExtracao
{
    public function __construct(private readonly ProcessarAnaliseTextoDocumentoAction $processar)
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
