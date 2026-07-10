<?php

declare(strict_types=1);

namespace App\Features\TipoDocumento\Actualizar;

use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ActualizarTipoDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('tipos_documento'));

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $uuid = $this->route('tipos_documento');

        return [
            'nome' => ['required', 'string', 'max:255', Rule::unique('tipos_documento', 'nome')->ignore($uuid)],
            'descricao' => ['required', 'string'],
            'id_categoria' => ['required', 'string', 'uuid', Rule::exists('categorias_documento', 'id')],
            'posicao_empresa_mae' => ['required', 'string', Rule::in(array_column(PosicaoEmpresaMae::cases(), 'value'))],
            'espera_data_documento' => ['required', 'boolean'],
            'espera_fornecedor' => ['required', 'boolean'],
            'espera_cliente' => ['required', 'boolean'],
            'espera_valor' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('espera_data_documento') && ! $this->boolean('espera_fornecedor') && ! $this->boolean('espera_cliente') && ! $this->boolean('espera_valor')) {
                $validator->errors()->add('espera_data_documento', 'Pelo menos um dos campos espera_* tem de ser verdadeiro.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do Tipo de Documento é obrigatório.',
            'nome.string' => 'O nome do Tipo de Documento deve ser texto.',
            'nome.max' => 'O nome do Tipo de Documento não pode ter mais de 255 caracteres.',
            'nome.unique' => 'Já existe um Tipo de Documento com este nome.',
            'descricao.required' => 'A descrição do Tipo de Documento é obrigatória.',
            'descricao.string' => 'A descrição do Tipo de Documento deve ser texto.',
            'id_categoria.required' => 'A Categoria é obrigatória.',
            'id_categoria.string' => 'O identificador da Categoria deve ser texto.',
            'id_categoria.uuid' => 'O identificador da Categoria deve ser um UUID válido.',
            'id_categoria.exists' => 'A Categoria indicada não existe.',
            'posicao_empresa_mae.required' => 'A posição da empresa-mãe é obrigatória.',
            'posicao_empresa_mae.string' => 'A posição da empresa-mãe deve ser texto.',
            'posicao_empresa_mae.in' => 'A posição da empresa-mãe indicada não é válida.',
            'espera_data_documento.required' => 'O campo espera_data_documento é obrigatório.',
            'espera_data_documento.boolean' => 'O campo espera_data_documento deve ser verdadeiro ou falso.',
            'espera_fornecedor.required' => 'O campo espera_fornecedor é obrigatório.',
            'espera_fornecedor.boolean' => 'O campo espera_fornecedor deve ser verdadeiro ou falso.',
            'espera_cliente.required' => 'O campo espera_cliente é obrigatório.',
            'espera_cliente.boolean' => 'O campo espera_cliente deve ser verdadeiro ou falso.',
            'espera_valor.required' => 'O campo espera_valor é obrigatório.',
            'espera_valor.boolean' => 'O campo espera_valor deve ser verdadeiro ou falso.',
        ];
    }
}
