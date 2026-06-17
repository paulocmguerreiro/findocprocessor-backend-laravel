<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Ver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class VerCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('categorias_documento'));

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
