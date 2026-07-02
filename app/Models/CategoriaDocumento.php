<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FiltravelPorEstadoRegisto;
use App\Models\Concerns\RegistaActividade;
use App\Policies\CategoriaDocumentoPolicy;
use App\Shared\Enums\TipoMovimento;
use Database\Factories\CategoriaDocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read string       $id
 * @property-read string       $nome
 * @property-read string       $slug
 * @property-read TipoMovimento $tipo_movimento
 * @property-read Carbon       $created_at
 * @property-read Carbon       $updated_at
 * @property-read ?Carbon      $deleted_at
 */
#[Table('categorias_documento')]
#[Fillable(['nome', 'slug', 'tipo_movimento'])]
#[UsePolicy(CategoriaDocumentoPolicy::class)]
class CategoriaDocumento extends Model
{
    /** @use HasFactory<CategoriaDocumentoFactory> */
    use FiltravelPorEstadoRegisto, HasFactory, HasUuids, RegistaActividade, SoftDeletes;

    #[\Override]
    protected function casts(): array
    {
        return [
            'tipo_movimento' => TipoMovimento::class,
        ];
    }
}
