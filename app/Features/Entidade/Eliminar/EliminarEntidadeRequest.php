<?php

declare(strict_types=1);

namespace App\Features\Entidade\Eliminar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class EliminarEntidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('delete', $this->route('entidade'));

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
