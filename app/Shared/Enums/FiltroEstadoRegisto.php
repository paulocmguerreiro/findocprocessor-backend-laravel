<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Filtro de estado de registo para modelos com SoftDeletes.
 *
 * Permite às listagens escolher se devolvem todos os registos, apenas os
 * activos (não eliminados) ou apenas os inactivos (soft-deleted).
 */
enum FiltroEstadoRegisto: string
{
    case Todos = 'todos';
    case SomenteAtivos = 'somente_ativos';
    case SomenteInativos = 'somente_inativos';
}
