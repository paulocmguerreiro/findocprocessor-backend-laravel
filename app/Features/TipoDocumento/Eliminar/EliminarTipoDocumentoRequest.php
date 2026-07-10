<?php

declare(strict_types=1);

namespace App\Features\TipoDocumento\Eliminar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class EliminarTipoDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('delete', $this->route('tipos_documento'));

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
