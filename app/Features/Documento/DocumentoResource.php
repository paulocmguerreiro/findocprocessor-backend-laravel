<?php

declare(strict_types=1);

namespace App\Features\Documento;

use App\Features\CategoriaDocumento\CategoriaDocumentoResource;
use App\Features\Entidade\EntidadeResource;
use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Documento */
final class DocumentoResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     estado: string,
     *     id_responsavel: int|null,
     *     fornecedor: EntidadeResource,
     *     cliente: EntidadeResource,
     *     categoria: CategoriaDocumentoResource,
     *     valor: float|null,
     *     data_documento: string|null,
     *     nome_ficheiro_original: string,
     *     hash_sha256: string,
     *     historico: AnonymousResourceCollection,
     *     etapa_extracao: string|null,
     *     criado_em: string,
     *     actualizado_em: string
     * }
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        /** @var string|null $etapaExtracao */
        $etapaExtracao = $this->whenLoaded('extracao', fn (): ?string => $this->extracao?->etapa_extracao->value);

        return [
            'id' => $this->id,
            'estado' => $this->estado->value,
            'id_responsavel' => $this->id_responsavel,
            'fornecedor' => EntidadeResource::make($this->whenLoaded('fornecedor')),
            'cliente' => EntidadeResource::make($this->whenLoaded('cliente')),
            'categoria' => CategoriaDocumentoResource::make($this->whenLoaded('categoria')),
            'valor' => $this->valor !== null ? (float) $this->valor : null,
            'data_documento' => $this->data_documento?->format('Y-m-d'),
            'nome_ficheiro_original' => $this->nome_ficheiro_original,
            'hash_sha256' => $this->hash_sha256,
            'historico' => EtapaDocumentoResource::collection($this->whenLoaded('historico')),
            'etapa_extracao' => $etapaExtracao,
            'criado_em' => $this->created_at->toIso8601String(),
            'actualizado_em' => $this->updated_at->toIso8601String(),
        ];
    }
}
