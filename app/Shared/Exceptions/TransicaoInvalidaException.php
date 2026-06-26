<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use App\Shared\Enums\EstadoDocumento;
use DomainException;

/**
 * Transição de estado do Documento não permitida pelo mapa central
 * (`RegraTransicaoEstado`). Estende `DomainException` — o exception handler
 * converte-a automaticamente em `422 Unprocessable Entity`.
 */
final class TransicaoInvalidaException extends DomainException
{
    public static function entre(EstadoDocumento $de, EstadoDocumento $para): self
    {
        return new self(sprintf(
            'Transição de estado inválida: de "%s" para "%s".',
            $de->value,
            $para->value,
        ));
    }
}
