<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Listar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarCategoriasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(array_column(CampoOrdenacaoCategorias::cases(), 'value'))],
            'cursor' => ['sometimes', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'per_page.integer' => 'O número de registos por página deve ser um número inteiro.',
            'per_page.min' => 'O número de registos por página deve ser pelo menos 1.',
            'per_page.max' => 'O número de registos por página não pode ser superior a 100.',
            'sort.string' => 'O campo de ordenação deve ser texto.',
            'sort.in' => 'O campo de ordenação indicado não é válido.',
            'cursor.string' => 'O cursor de paginação deve ser texto.',
        ];
    }
}
