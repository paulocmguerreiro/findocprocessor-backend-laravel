<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento;

use App\Models\CategoriaDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CategoriaDocumento */
final class CategoriaDocumentoResource extends JsonResource
{
    /**
     * @return array{id: string, nome: string, slug: string, tipo_movimento: string}
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'slug' => $this->slug,
            'tipo_movimento' => $this->tipo_movimento->value,
        ];
    }
}
