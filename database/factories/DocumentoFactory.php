<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
use App\Models\User;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Documento>
 */
class DocumentoFactory extends Factory
{
    #[\Override]
    protected $model = Documento::class;

    /**
     * Estado base: documento Processado (registo manual — todos os campos preenchidos).
     *
     * @return array{
     *     estado: EstadoDocumento,
     *     id_responsavel: Factory<User>,
     *     id_fornecedor: Factory<Entidade>,
     *     id_cliente: Factory<Entidade>,
     *     id_categoria: Factory<CategoriaDocumento>,
     *     valor: float,
     *     data_documento: string,
     *     nome_ficheiro_original: string,
     *     disco_storage: string,
     *     nome_ficheiro_storage: string,
     *     hash_sha256: string
     * }
     */
    public function definition(): array
    {
        return [
            'estado' => EstadoDocumento::Processado,
            'id_responsavel' => User::factory(),
            'id_fornecedor' => Entidade::factory()->fornecedor(),
            'id_cliente' => Entidade::factory()->cliente(),
            'id_categoria' => CategoriaDocumento::factory(),
            'valor' => $this->faker->randomFloat(2, 0, 9999),
            'data_documento' => $this->faker->date(),
            'nome_ficheiro_original' => $this->faker->word().'.pdf',
            'disco_storage' => 'processado',
            'nome_ficheiro_storage' => $this->faker->uuid().'.pdf',
            'hash_sha256' => hash('sha256', $this->faker->unique()->sha256()),
        ];
    }

    public function pendente(): static
    {
        return $this->semDadosDeDominio(EstadoDocumento::Pendente, 'entrada');
    }

    public function aguardaEnvio(): static
    {
        return $this->semDadosDeDominio(EstadoDocumento::AguardaEnvio, 'entrada');
    }

    public function enviado(): static
    {
        return $this->semDadosDeDominio(EstadoDocumento::Enviado, 'enviado');
    }

    public function aguardaResposta(): static
    {
        return $this->semDadosDeDominio(EstadoDocumento::AguardaResposta, 'enviado');
    }

    public function processado(): static
    {
        return $this->state([
            'estado' => EstadoDocumento::Processado,
            'disco_storage' => 'processado',
        ]);
    }

    public function erro(): static
    {
        return $this->semDadosDeDominio(EstadoDocumento::Erro, 'erro');
    }

    public function perigoso(): static
    {
        return $this->semDadosDeDominio(EstadoDocumento::Perigoso, 'perigoso');
    }

    /**
     * Estados parciais: sem dados de domínio (FKs/valor/data a null), com o
     * disco_storage do respectivo estado do ciclo de vida.
     */
    private function semDadosDeDominio(EstadoDocumento $estado, string $disco): static
    {
        return $this->state([
            'estado' => $estado,
            'disco_storage' => $disco,
            'id_fornecedor' => null,
            'id_cliente' => null,
            'id_categoria' => null,
            'valor' => null,
            'data_documento' => null,
        ]);
    }
}
