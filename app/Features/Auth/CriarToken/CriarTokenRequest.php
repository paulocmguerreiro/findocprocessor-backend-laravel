<?php

declare(strict_types=1);

namespace App\Features\Auth\CriarToken;

use Illuminate\Foundation\Http\FormRequest;

final class CriarTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'nome_token' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'nome_token.required' => 'O nome do token é obrigatório.',
            'nome_token.max' => 'O nome do token não pode exceder 255 caracteres.',
        ];
    }
}
