<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Criar;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class CriarUtilizadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('create', User::class);

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'role' => ['sometimes', 'nullable', 'string', Rule::exists(Role::class, 'name')],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'email.required' => 'O email é obrigatório.',
            'email.email' => 'O email indicado não é válido.',
            'email.max' => 'O email não pode ter mais de 255 caracteres.',
            'email.unique' => 'Já existe um utilizador com este email.',
            'password.required' => 'A palavra-passe é obrigatória.',
            'password.confirmed' => 'A confirmação da palavra-passe não coincide.',
            'password.min' => 'A palavra-passe deve ter pelo menos 8 caracteres.',
            'password.mixed' => 'A palavra-passe deve conter letras maiúsculas e minúsculas.',
            'password.letters' => 'A palavra-passe deve conter pelo menos uma letra.',
            'password.numbers' => 'A palavra-passe deve conter pelo menos um número.',
            'password.symbols' => 'A palavra-passe deve conter pelo menos um símbolo.',
            'role.string' => 'O role deve ser texto.',
            'role.exists' => 'O role indicado não existe.',
        ];
    }
}
