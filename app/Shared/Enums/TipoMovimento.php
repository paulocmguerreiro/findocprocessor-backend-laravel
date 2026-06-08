<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum TipoMovimento: string
{
    case Debito = 'debito';
    case Credito = 'credito';
    case Neutro = 'neutro';
}
