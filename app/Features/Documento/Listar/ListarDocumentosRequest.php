<?php

declare(strict_types=1);

namespace App\Features\Documento\Listar;

use App\Models\Documento;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ListarDocumentosRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('viewAny', Documento::class);

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(array_column(CampoOrdenacaoDocumentos::cases(), 'value'))],
            'direction' => ['sometimes', 'string', Rule::in(array_column(DirecaoOrdenacao::cases(), 'value'))],
            'estado' => ['sometimes', 'string', Rule::in(array_column(EstadoDocumento::cases(), 'value'))],
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
            'sort.in' => 'O campo de ordenação indicado não é válido.',
            'direction.in' => 'A direcção de ordenação indicada não é válida.',
            'estado.in' => 'O estado indicado não é válido.',
        ];
    }
}
