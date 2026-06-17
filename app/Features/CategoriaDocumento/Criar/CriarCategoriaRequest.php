<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Criar;

use App\Models\CategoriaDocumento;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CriarCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('create', CategoriaDocumento::class);

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categorias_documento', 'slug')],
            'tipo_movimento' => ['required', 'string', Rule::in(array_column(TipoMovimento::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome da Categoria é obrigatório.',
            'nome.string' => 'O nome da Categoria deve ser texto.',
            'nome.max' => 'O nome da Categoria não pode ter mais de 255 caracteres.',
            'slug.required' => 'O identificador da URL da Categoria é obrigatório.',
            'slug.string' => 'O identificador da URL da Categoria deve ser texto.',
            'slug.max' => 'O identificador da URL da Categoria não pode ter mais de 255 caracteres.',
            'slug.unique' => 'Já existe uma Categoria com este identificador da URL.',
            'tipo_movimento.required' => 'O tipo de movimento é obrigatório.',
            'tipo_movimento.string' => 'O tipo de movimento deve ser texto.',
            'tipo_movimento.in' => 'O tipo de movimento indicado não é válido.',
        ];
    }
}
