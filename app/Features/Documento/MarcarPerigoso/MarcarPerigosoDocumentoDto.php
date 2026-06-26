<?php

declare(strict_types=1);

namespace App\Features\Documento\MarcarPerigoso;

use InvalidArgumentException;

/**
 * Motivo da marcação como `Perigoso` (pré-scan em `Pendente` ou guardrail em
 * `AguardaResposta`). Construído pelo pipeline.
 */
final readonly class MarcarPerigosoDocumentoDto
{
    /**
     * @throws InvalidArgumentException
     */
    public function __construct(public string $motivo)
    {
        if (trim($this->motivo) === '') {
            throw new InvalidArgumentException('motivo não pode ser vazio.');
        }
    }
}
