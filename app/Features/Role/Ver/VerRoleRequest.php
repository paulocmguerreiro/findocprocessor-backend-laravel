<?php

declare(strict_types=1);

namespace App\Features\Role\Ver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class VerRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('role'));

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
