<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Filtro de estado de registo para modelos com SoftDeletes.
 *
 * Permite às listagens escolher se devolvem apenas registos activos
 * (não eliminados), apenas inactivos (soft-deleted) ou todos.
 */
enum FiltroEstadoRegisto: string
{
    case Activos = 'activos';
    case Inactivos = 'inactivos';
    case Todos = 'todos';
}
