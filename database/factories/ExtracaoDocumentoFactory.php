<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EtapaExtracao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtracaoDocumento>
 */
class ExtracaoDocumentoFactory extends Factory
{
    #[\Override]
    protected $model = ExtracaoDocumento::class;

    /**
     * Estado base: etapa Pendente, sem tentativas nem lease nem dados extraídos.
     *
     * @return array{
     *     id_documento: Factory<Documento>,
     *     etapa_extracao: EtapaExtracao,
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
            'etapa_extracao' => EtapaExtracao::Pendente,
            'extracao_reclamada_em' => null,
            'extracao_tentativas' => 0,
            'texto_extraido' => null,
            'dados_json' => null,
        ];
    }

    public function necessitaOcr(): static
    {
        return $this->state(['etapa_extracao' => EtapaExtracao::NecessitaOcr]);
    }

    public function textoPronto(): static
    {
        return $this->state([
            'etapa_extracao' => EtapaExtracao::TextoPronto,
            'texto_extraido' => $this->faker->paragraph(),
        ]);
    }

    public function necessitaCloud(): static
    {
        return $this->state(['etapa_extracao' => EtapaExtracao::NecessitaCloud]);
    }

    public function concluido(): static
    {
        return $this->state([
            'etapa_extracao' => EtapaExtracao::Concluido,
            'texto_extraido' => $this->faker->paragraph(),
            'dados_json' => ['nif' => $this->faker->numerify('#########')],
        ]);
    }

    public function falhado(): static
    {
        return $this->state([
            'etapa_extracao' => EtapaExtracao::Falhado,
            'extracao_tentativas' => 3,
        ]);
    }

    /** Lease reclamado — testa o campo, mesmo sem orquestrador real. */
    public function reclamada(): static
    {
        return $this->state(['extracao_reclamada_em' => now()]);
    }
}
