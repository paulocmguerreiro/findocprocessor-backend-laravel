<?php

declare(strict_types=1);

namespace App\Features\Role\Actualizar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class ActualizarRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('role'));

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'nome' => ['sometimes', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissoes' => ['required', 'array'],
            'permissoes.*' => ['string', Rule::exists(Permission::class, 'name')],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'nome.unique' => 'Já existe um role com este nome.',
            'nome.max' => 'O nome do role não pode ter mais de 255 caracteres.',
            'permissoes.required' => 'A lista de permissões é obrigatória.',
            'permissoes.array' => 'As permissões devem ser uma lista.',
            'permissoes.*.exists' => 'Uma ou mais permissões indicadas não existem.',
        ];
    }
}
