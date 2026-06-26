<?php

declare(strict_types=1);

namespace App\Features\Documento\Reprocessar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ReprocessarDocumentoRequest extends FormRequest
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
            'modo' => ['required', Rule::enum(ModoReprocessamento::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'modo.required' => 'O modo de reprocessamento é obrigatório.',
            'modo.enum' => 'O modo de reprocessamento indicado não é válido.',
        ];
    }
}
