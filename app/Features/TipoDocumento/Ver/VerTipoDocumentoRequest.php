<?php

declare(strict_types=1);

namespace App\Features\TipoDocumento\Ver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class VerTipoDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('tipos_documento'));

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
