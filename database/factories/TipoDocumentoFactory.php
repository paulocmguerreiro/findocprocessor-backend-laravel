<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoDocumento>
 */
class TipoDocumentoFactory extends Factory
{
    #[\Override]
    protected $model = TipoDocumento::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->unique()->words(3, true),
            'descricao' => $this->faker->sentence(),
            'id_categoria' => CategoriaDocumento::factory(),
            'posicao_empresa_mae' => $this->faker->randomElement(PosicaoEmpresaMae::cases()),
            'espera_data_documento' => true,
            'espera_fornecedor' => true,
            'espera_cliente' => true,
            'espera_valor' => true,
        ];
    }
}
