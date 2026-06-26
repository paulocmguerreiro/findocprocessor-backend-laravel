<?php

declare(strict_types=1);

namespace App\Features\Documento\Descarregar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class DescarregarDocumentoRequest extends FormRequest
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
