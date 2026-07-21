<?php

declare(strict_types=1);

namespace App\Features\Entidade\Agrupar;

use App\Models\Entidade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class AgruparEntidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('agrupar', Entidade::class);

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
