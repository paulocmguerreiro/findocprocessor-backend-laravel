<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Listar;

enum CampoOrdenacaoUtilizadores: string
{
    case Nome = 'name';
    case Email = 'email';
    case CriadoEm = 'created_at';
}
