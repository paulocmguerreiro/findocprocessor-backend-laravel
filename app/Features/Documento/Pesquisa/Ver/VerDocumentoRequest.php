<?php

declare(strict_types=1);

namespace App\Features\Documento\Pesquisa\Ver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class VerDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('documento'));

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
