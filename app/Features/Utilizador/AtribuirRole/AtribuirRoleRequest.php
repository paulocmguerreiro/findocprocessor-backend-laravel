<?php

declare(strict_types=1);

namespace App\Features\Utilizador\AtribuirRole;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

final class AtribuirRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('atribuirRole', $this->route('utilizador'));

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::exists(Role::class, 'name')],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'role.required' => 'O nome do role é obrigatório.',
            'role.exists' => 'O role indicado não existe.',
        ];
    }
}
