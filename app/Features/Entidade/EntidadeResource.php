<?php

declare(strict_types=1);

namespace App\Features\Entidade;

use App\Models\Entidade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Entidade */
final class EntidadeResource extends JsonResource
{
    /**
     * @return array{id: string, nome: string, nif: string, e_cliente: bool, e_fornecedor: bool, e_empresa_aplicacao: bool}
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'nif' => $this->nif,
            'e_cliente' => $this->e_cliente,
            'e_fornecedor' => $this->e_fornecedor,
            'e_empresa_aplicacao' => $this->e_empresa_aplicacao,
        ];
    }
}
