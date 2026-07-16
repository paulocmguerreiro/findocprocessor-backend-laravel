<?php

declare(strict_types=1);

namespace App\Features\Documento;

use App\Models\EtapaDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EtapaDocumento */
final class EtapaDocumentoResource extends JsonResource
{
    /**
     * @return array{
     *     estado: string,
     *     resultado: string|null,
     *     motivo: string|null,
     *     id_utilizador: int|null,
     *     criado_em: string
     * }
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'estado' => $this->estado->value,
            'resultado' => $this->resultado?->value,
            'motivo' => $this->motivo,
            'id_utilizador' => $this->id_utilizador,
            'criado_em' => $this->created_at->toIso8601String(),
        ];
    }
}
