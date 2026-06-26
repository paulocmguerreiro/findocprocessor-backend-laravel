<?php

declare(strict_types=1);

namespace App\Features\Documento\MarcarErro;

use InvalidArgumentException;

/**
 * Motivo da falha na transição `AguardaResposta → Erro`. Construído pelo pipeline.
 */
final readonly class MarcarErroDocumentoDto
{
    /**
     * @throws InvalidArgumentException
     */
    public function __construct(public string $mensagemErro)
    {
        if (trim($this->mensagemErro) === '') {
            throw new InvalidArgumentException('mensagemErro não pode ser vazia.');
        }
    }
}
