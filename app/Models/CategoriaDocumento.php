<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\CategoriaDocumentoPolicy;
use App\Shared\Enums\TipoMovimento;
use Database\Factories\CategoriaDocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property-read string $id
 * @property-read string $nome
 * @property-read string $slug
 * @property-read TipoMovimento $tipo_movimento
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[Table('categorias_documento')]
#[Fillable(['nome', 'slug', 'tipo_movimento'])]
#[UsePolicy(CategoriaDocumentoPolicy::class)]
class CategoriaDocumento extends Model
{
    /** @use HasFactory<CategoriaDocumentoFactory> */
    use HasFactory;

    use HasUuids;
    use LogsActivity;

    #[\Override]
    protected function casts(): array
    {
        return [
            'tipo_movimento' => TipoMovimento::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
