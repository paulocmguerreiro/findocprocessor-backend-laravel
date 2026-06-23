<?php

declare(strict_types=1);

namespace App\Features\Role\Criar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class CriarRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('create', Role::class);

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')],
            'permissoes' => ['required', 'array'],
            'permissoes.*' => ['string', Rule::exists(Permission::class, 'name')],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do role é obrigatório.',
            'nome.unique' => 'Já existe um role com este nome.',
            'nome.max' => 'O nome do role não pode ter mais de 255 caracteres.',
            'permissoes.required' => 'A lista de permissões é obrigatória.',
            'permissoes.array' => 'As permissões devem ser uma lista.',
            'permissoes.*.exists' => 'Uma ou mais permissões indicadas não existem.',
        ];
    }
}
