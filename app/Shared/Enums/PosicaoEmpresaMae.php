<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum PosicaoEmpresaMae: string
{
    case Fornecedor = 'fornecedor';
    case Cliente = 'cliente';
}
