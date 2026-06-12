<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Actualizar;

use App\Shared\Enums\TipoMovimento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ActualizarCategoriaRequest extends FormRequest
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
        $uuid = $this->route('categorias_documento');

        return [
            'nome' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('categorias_documento', 'slug')->ignore($uuid)],
            'tipo_movimento' => ['sometimes', 'string', Rule::in(array_column(TipoMovimento::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'nome.string' => 'O nome da Categoria deve ser texto.',
            'nome.max' => 'O nome da Categoria não pode ter mais de 255 caracteres.',
            'slug.string' => 'O identificador da URL da Categoria deve ser texto.',
            'slug.max' => 'O identificador da URL da Categoria não pode ter mais de 255 caracteres.',
            'slug.unique' => 'Já existe uma Categoria com este identificador da URL.',
            'tipo_movimento.string' => 'O tipo de movimento deve ser texto.',
            'tipo_movimento.in' => 'O tipo de movimento indicado não é válido.',
        ];
    }
}
