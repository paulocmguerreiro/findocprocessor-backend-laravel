<?php

declare(strict_types=1);

namespace App\Features\TipoDocumento;

use App\Features\CategoriaDocumento\CategoriaDocumentoResource;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TipoDocumento */
final class TipoDocumentoResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     nome: string,
     *     descricao: string,
     *     categoria: CategoriaDocumentoResource,
     *     tipo_movimento: string|null,
     *     posicao_empresa_mae: string,
     *     espera_data_documento: bool,
     *     espera_fornecedor: bool,
     *     espera_cliente: bool,
     *     espera_valor: bool,
     *     criado_em: string,
     *     actualizado_em: string
     * }
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'categoria' => CategoriaDocumentoResource::make($this->whenLoaded('categoria')),
            'tipo_movimento' => $this->categoria?->tipo_movimento?->value,
            'posicao_empresa_mae' => $this->posicao_empresa_mae->value,
            'espera_data_documento' => $this->espera_data_documento,
            'espera_fornecedor' => $this->espera_fornecedor,
            'espera_cliente' => $this->espera_cliente,
            'espera_valor' => $this->espera_valor,
            'criado_em' => $this->created_at->toIso8601String(),
            'actualizado_em' => $this->updated_at->toIso8601String(),
        ];
    }
}
