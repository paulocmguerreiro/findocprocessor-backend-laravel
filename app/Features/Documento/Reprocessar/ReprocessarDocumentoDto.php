<?php

declare(strict_types=1);

namespace App\Features\Documento\Reprocessar;

/**
 * Parâmetros da transição `Erro → AguardaEnvio`. Exposta via HTTP — `fromRequest`
 * adicionado com o FormRequest (camada HTTP).
 */
final readonly class ReprocessarDocumentoDto
{
    public function __construct(public ModoReprocessamento $modo) {}
}
