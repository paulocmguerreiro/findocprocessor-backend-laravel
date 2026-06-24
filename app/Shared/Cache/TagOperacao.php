<?php

declare(strict_types=1);

namespace App\Shared\Cache;

enum TagOperacao: string
{
    case Ver = 'ver';
    case Listar = 'listar';
}
