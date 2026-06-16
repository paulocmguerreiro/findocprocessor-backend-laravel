<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Listar;

use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListarCategoriasRequest extends FormRequest
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
            'direction' => ['sometimes', 'string', Rule::in(array_column(DirecaoOrdenacao::cases(), 'value'))],
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
            'direction.string' => 'A direcção de ordenação deve ser texto.',
            'direction.in' => 'A direcção de ordenação indicada não é válida.',
            'cursor.string' => 'O cursor de paginação deve ser texto.',
        ];
    }
}
