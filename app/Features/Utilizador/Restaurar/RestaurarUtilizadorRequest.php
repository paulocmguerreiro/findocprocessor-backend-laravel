<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Restaurar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class RestaurarUtilizadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('restore', $this->route('utilizador'));

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
