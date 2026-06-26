<?php

declare(strict_types=1);

namespace App\Features\Documento\Corrigir;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class CorrigirDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('documento'));

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id_fornecedor' => ['required', 'uuid', Rule::exists('entidades', 'id')],
            'id_cliente' => ['required', 'uuid', Rule::exists('entidades', 'id')],
            'id_categoria' => ['required', 'uuid', Rule::exists('categorias_documento', 'id')],
            'valor' => ['required', 'numeric', 'min:0'],
            'data_documento' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'id_fornecedor.required' => 'O fornecedor é obrigatório.',
            'id_fornecedor.uuid' => 'O fornecedor indicado é inválido.',
            'id_fornecedor.exists' => 'O fornecedor indicado não existe.',
            'id_cliente.required' => 'O cliente é obrigatório.',
            'id_cliente.uuid' => 'O cliente indicado é inválido.',
            'id_cliente.exists' => 'O cliente indicado não existe.',
            'id_categoria.required' => 'A categoria é obrigatória.',
            'id_categoria.uuid' => 'A categoria indicada é inválida.',
            'id_categoria.exists' => 'A categoria indicada não existe.',
            'valor.required' => 'O valor é obrigatório.',
            'valor.numeric' => 'O valor deve ser um número.',
            'valor.min' => 'O valor não pode ser negativo.',
            'data_documento.required' => 'A data do documento é obrigatória.',
            'data_documento.date' => 'A data do documento é inválida.',
        ];
    }
}
