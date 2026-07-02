<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Restaurar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class RestaurarCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('restore', $this->route('categorias_documento'));

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
