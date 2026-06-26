<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\EstadoDocumento;
use Database\Factories\EtapaDocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read string $id_documento
 * @property-read EstadoDocumento $estado
 * @property-read ?string $motivo
 * @property-read ?int $id_utilizador
 * @property-read Carbon $created_at
 * @property-read Documento $documento
 * @property-read ?User $utilizador
 */
#[Table('etapas_documento')]
#[Fillable(['id_documento', 'estado', 'motivo', 'id_utilizador'])]
class EtapaDocumento extends Model
{
    /** @use HasFactory<EtapaDocumentoFactory> */
    use HasFactory;

    use HasUuids;

    /** Append-only: sem updated_at. */
    public const UPDATED_AT = null;

    /** @return array{estado: class-string<EstadoDocumento>} */
    #[\Override]
    protected function casts(): array
    {
        return ['estado' => EstadoDocumento::class];
    }

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'id_documento');
    }

    /** @return BelongsTo<User, $this> */
    public function utilizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_utilizador');
    }
}
