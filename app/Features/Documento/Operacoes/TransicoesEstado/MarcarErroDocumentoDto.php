<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\TransicoesEstado;

use InvalidArgumentException;

/**
 * Motivo da falha na transição para `Erro` (a partir de qualquer estado de
 * análise). Construído pelo pipeline.
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
