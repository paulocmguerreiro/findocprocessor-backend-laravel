<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExtracaoDocumentoFactory;
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
 * @property-read ?Carbon $extracao_reclamada_em
 * @property-read int $extracao_tentativas
 * @property-read ?string $texto_extraido
 * @property-read ?array{
 *     data_documento?: string,
 *     fornecedor?: array{nif?: string, nome?: string},
 *     cliente?: array{nif?: string, nome?: string},
 *     valor?: float,
 * } $dados_json
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read Documento $documento
 */
#[Table('extracoes_documento')]
#[Fillable([
    'id_documento', 'extracao_reclamada_em',
    'extracao_tentativas', 'texto_extraido', 'dados_json',
])]
class ExtracaoDocumento extends Model
{
    /** @use HasFactory<ExtracaoDocumentoFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array{
     *     extracao_reclamada_em: string,
     *     extracao_tentativas: string,
     *     dados_json: string
     * }
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'extracao_reclamada_em' => 'datetime',
            'extracao_tentativas' => 'integer',
            'dados_json' => 'array',
        ];
    }

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'id_documento');
    }
}
