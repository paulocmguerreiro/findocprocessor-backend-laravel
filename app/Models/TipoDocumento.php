<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use App\Policies\TipoDocumentoPolicy;
use App\Shared\Enums\PosicaoEmpresaMae;
use Database\Factories\TipoDocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read string             $id
 * @property-read string             $nome
 * @property-read string             $descricao
 * @property-read string             $id_categoria
 * @property-read PosicaoEmpresaMae  $posicao_empresa_mae
 * @property-read bool               $espera_data_documento
 * @property-read bool               $espera_fornecedor
 * @property-read bool               $espera_cliente
 * @property-read bool               $espera_valor
 * @property-read Carbon             $created_at
 * @property-read Carbon             $updated_at
 * @property-read ?CategoriaDocumento $categoria
 */
#[Table('tipos_documento')]
#[Fillable([
    'nome',
    'descricao',
    'id_categoria',
    'posicao_empresa_mae',
    'espera_data_documento',
    'espera_fornecedor',
    'espera_cliente',
    'espera_valor',
])]
#[UsePolicy(TipoDocumentoPolicy::class)]
class TipoDocumento extends Model
{
    /** @use HasFactory<TipoDocumentoFactory> */
    use HasFactory, HasUuids, RegistaActividade;

    /**
     * @return array{
     *     posicao_empresa_mae: class-string<PosicaoEmpresaMae>,
     *     espera_data_documento: string,
     *     espera_fornecedor: string,
     *     espera_cliente: string,
     *     espera_valor: string,
     * }
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'posicao_empresa_mae' => PosicaoEmpresaMae::class,
            'espera_data_documento' => 'boolean',
            'espera_fornecedor' => 'boolean',
            'espera_cliente' => 'boolean',
            'espera_valor' => 'boolean',
        ];
    }

    /** @return BelongsTo<CategoriaDocumento, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaDocumento::class, 'id_categoria')->withTrashed();
    }
}
