<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CategoriaDocumento;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CategoriaDocumento>
 */
class CategoriaDocumentoFactory extends Factory
{
    #[\Override]
    protected $model = CategoriaDocumento::class;

    public function definition(): array
    {
        $nome = $this->faker->word().' '.$this->faker->word();

        return [
            'nome' => $nome,
            'slug' => Str::slug($nome),
            'tipo_movimento' => $this->faker->randomElement(TipoMovimento::cases()),
        ];
    }

    public function comMovimentoDebito(): static
    {
        return $this->state(['tipo_movimento' => TipoMovimento::Debito]);
    }

    public function comMovimentoCredito(): static
    {
        return $this->state(['tipo_movimento' => TipoMovimento::Credito]);
    }

    public function comMovimentoNeutro(): static
    {
        return $this->state(['tipo_movimento' => TipoMovimento::Neutro]);
    }
}
