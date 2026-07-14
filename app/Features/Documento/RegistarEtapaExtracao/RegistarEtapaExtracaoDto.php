<?php

declare(strict_types=1);

namespace App\Features\Documento\RegistarEtapaExtracao;

use App\Shared\Enums\EtapaExtracao;
use App\Shared\Enums\ResultadoEtapa;
use InvalidArgumentException;

/**
 * Passo de IA a registar (upsert em `extracoes_documento` + `EtapaDocumento`).
 * Construído programaticamente pelo pipeline (futuro orquestrador, #97/#98) —
 * sem `fromRequest`, VO interno nunca originado de HTTP.
 */
final readonly class RegistarEtapaExtracaoDto
{
    /**
     * @param  ?array<string, mixed>  $dadosJson
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public EtapaExtracao $etapaExtracao,
        public ResultadoEtapa $resultado,
        public ?string $motivo = null,
        public ?string $textoExtraido = null,
        public ?array $dadosJson = null,
        public bool $reclamar = false,
        public bool $incrementarTentativas = false,
    ) {
        if ($this->resultado === ResultadoEtapa::Falha && trim((string) $this->motivo) === '') {
            throw new InvalidArgumentException('motivo não pode ser vazio quando resultado é Falha.');
        }
    }
}
