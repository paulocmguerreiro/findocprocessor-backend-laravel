<?php

declare(strict_types=1);

namespace App\Features\Role\Eliminar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class EliminarRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('delete', $this->route('role'));

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
