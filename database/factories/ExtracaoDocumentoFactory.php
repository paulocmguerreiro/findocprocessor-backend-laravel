<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtracaoDocumento>
 */
class ExtracaoDocumentoFactory extends Factory
{
    #[\Override]
    protected $model = ExtracaoDocumento::class;

    /**
     * Estado base: scratch space vazio — sem tentativas, sem lease, sem dados
     * extraídos. A linha só existe enquanto o documento está em pipeline activo.
     *
     * @return array{
     *     id_documento: Factory<Documento>,
     *     extracao_reclamada_em: null,
     *     extracao_tentativas: int,
     *     texto_extraido: null,
     *     dados_json: null
     * }
     */
    public function definition(): array
    {
        return [
            'id_documento' => Documento::factory(),
            'extracao_reclamada_em' => null,
            'extracao_tentativas' => 0,
            'texto_extraido' => null,
            'dados_json' => null,
        ];
    }

    /** Lease reclamado — testa o campo, mesmo sem orquestrador real. */
    public function reclamada(): static
    {
        return $this->state(['extracao_reclamada_em' => now()]);
    }

    /** Scratch space preenchido com o payload de extracção (PII) — cobre a eliminação nos terminais. */
    public function comDadosExtraidos(): static
    {
        return $this->state([
            'texto_extraido' => $this->faker->paragraph(),
            'dados_json' => ['nif' => $this->faker->numerify('#########')],
        ]);
    }

    /** Contador de tentativas de extracção. */
    public function comTentativas(int $tentativas): static
    {
        return $this->state(['extracao_tentativas' => $tentativas]);
    }
}
