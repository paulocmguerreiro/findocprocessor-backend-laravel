<?php

declare(strict_types=1);

namespace App\Features\Entidade\Actualizar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ActualizarEntidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('entidade'));

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'nif' => ['required', 'string', 'max:20'],
            'e_cliente' => ['required', 'boolean'],
            'e_fornecedor' => ['required', 'boolean'],
            'e_empresa_aplicacao' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome da Entidade é obrigatório.',
            'nome.string' => 'O nome da Entidade deve ser texto.',
            'nome.max' => 'O nome da Entidade não pode ter mais de 255 caracteres.',
            'nif.required' => 'O NIF da Entidade é obrigatório.',
            'nif.string' => 'O NIF da Entidade deve ser texto.',
            'nif.max' => 'O NIF da Entidade não pode ter mais de 20 caracteres.',
            'e_cliente.required' => 'O campo "é cliente" é obrigatório.',
            'e_cliente.boolean' => 'O campo "é cliente" deve ser verdadeiro ou falso.',
            'e_fornecedor.required' => 'O campo "é fornecedor" é obrigatório.',
            'e_fornecedor.boolean' => 'O campo "é fornecedor" deve ser verdadeiro ou falso.',
            'e_empresa_aplicacao.required' => 'O campo "é empresa da aplicação" é obrigatório.',
            'e_empresa_aplicacao.boolean' => 'O campo "é empresa da aplicação" deve ser verdadeiro ou falso.',
        ];
    }
}
