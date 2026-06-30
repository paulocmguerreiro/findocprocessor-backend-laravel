<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Entidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entidade>
 */
class EntidadeFactory extends Factory
{
    #[\Override]
    protected $model = Entidade::class;

    /**
     * @return array{nome: string, nif: string, e_cliente: bool, e_fornecedor: bool, e_empresa_aplicacao: bool}
     */
    public function definition(): array
    {
        return [
            'nome' => $this->faker->company(),
            'nif' => $this->faker->numerify('#########'),
            'e_cliente' => false,
            'e_fornecedor' => false,
            'e_empresa_aplicacao' => false,
        ];
    }

    public function cliente(): static
    {
        return $this->state(['e_cliente' => true, 'e_fornecedor' => false, 'e_empresa_aplicacao' => false]);
    }

    public function fornecedor(): static
    {
        return $this->state(['e_cliente' => false, 'e_fornecedor' => true, 'e_empresa_aplicacao' => false]);
    }

    public function clienteEFornecedor(): static
    {
        return $this->state(['e_cliente' => true, 'e_fornecedor' => true, 'e_empresa_aplicacao' => false]);
    }

    public function empresaAplicacao(): static
    {
        return $this->state(['e_cliente' => true, 'e_fornecedor' => true, 'e_empresa_aplicacao' => true]);
    }

    public function inativa(): static
    {
        return $this->state(['deleted_at' => now()]);
    }
}
