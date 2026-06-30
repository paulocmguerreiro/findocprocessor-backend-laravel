<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Shared\Enums\FiltroEstadoRegisto;
use Illuminate\Database\Eloquent\Builder;

/**
 * Disponibiliza um scope transversal para filtrar registos pelo seu estado
 * de SoftDeletes a partir de um {@see FiltroEstadoRegisto}, à semelhança de
 * withTrashed()/onlyTrashed()/withoutTrashed() mas controlado por enum.
 *
 * Requer que o modelo use o trait Illuminate\Database\Eloquent\SoftDeletes.
 */
trait FiltravelPorEstadoRegisto
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeFiltrarPorEstadoRegisto(Builder $query, FiltroEstadoRegisto $filtro): void
    {
        match ($filtro) {
            FiltroEstadoRegisto::Activos => $query->withoutTrashed(),
            FiltroEstadoRegisto::Inactivos => $query->onlyTrashed(),
            FiltroEstadoRegisto::Todos => $query->withTrashed(),
        };
    }
}
