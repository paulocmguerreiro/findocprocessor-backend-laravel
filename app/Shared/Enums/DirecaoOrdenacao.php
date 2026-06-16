<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum DirecaoOrdenacao: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
