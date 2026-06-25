<?php

declare(strict_types=1);

namespace App\Features\Documento;

use App\Features\CategoriaDocumento\CategoriaDocumentoResource;
use App\Features\Entidade\EntidadeResource;
use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Documento */
final class DocumentoResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     status: string,
     *     fornecedor: EntidadeResource,
     *     cliente: EntidadeResource,
     *     categoria: CategoriaDocumentoResource,
     *     valor: float|null,
     *     data_documento: string|null,
     *     nome_ficheiro_original: string,
     *     hash_sha256: string,
     *     criado_em: string,
     *     actualizado_em: string
     * }
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'fornecedor' => EntidadeResource::make($this->whenLoaded('fornecedor')),
            'cliente' => EntidadeResource::make($this->whenLoaded('cliente')),
            'categoria' => CategoriaDocumentoResource::make($this->whenLoaded('categoria')),
            'valor' => $this->valor !== null ? (float) $this->valor : null,
            'data_documento' => $this->data_documento?->format('Y-m-d'),
            'nome_ficheiro_original' => $this->nome_ficheiro_original,
            'hash_sha256' => $this->hash_sha256,
            'criado_em' => $this->created_at->toIso8601String(),
            'actualizado_em' => $this->updated_at->toIso8601String(),
        ];
    }
}
