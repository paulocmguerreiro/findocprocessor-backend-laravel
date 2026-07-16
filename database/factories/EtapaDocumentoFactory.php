<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Documento;
use App\Models\EtapaDocumento;
use App\Models\User;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Enums\ResultadoEtapa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EtapaDocumento>
 */
class EtapaDocumentoFactory extends Factory
{
    #[\Override]
    protected $model = EtapaDocumento::class;

    /**
     * Estado base: etapa Pendente, passo automático (sem utilizador, sem motivo).
     *
     * @return array{
     *     id_documento: Factory<Documento>,
     *     estado: EstadoDocumento,
     *     motivo: null,
     *     id_utilizador: null
     * }
     */
    public function definition(): array
    {
        return [
            'id_documento' => Documento::factory(),
            'estado' => EstadoDocumento::Pendente,
            'motivo' => null,
            'id_utilizador' => null,
        ];
    }

    public function processado(): static
    {
        return $this->state(['estado' => EstadoDocumento::Processado]);
    }

    public function erro(): static
    {
        return $this->state([
            'estado' => EstadoDocumento::Erro,
            'motivo' => $this->faker->sentence(),
        ]);
    }

    public function perigoso(): static
    {
        return $this->state([
            'estado' => EstadoDocumento::Perigoso,
            'motivo' => $this->faker->sentence(),
        ]);
    }

    /** Passo do utilizador (não automático). */
    public function manual(): static
    {
        return $this->state([
            'estado' => EstadoDocumento::Processado,
            'id_utilizador' => User::factory(),
        ]);
    }

    /** Linha de IA (passo automático da extracao) — estado de negócio não muda; só grava o resultado. */
    public function passoIa(ResultadoEtapa $resultado = ResultadoEtapa::Sucesso): static
    {
        return $this->state(['resultado' => $resultado]);
    }
}
