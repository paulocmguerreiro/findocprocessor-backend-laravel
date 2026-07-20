<?php

declare(strict_types=1);

namespace App\Features\Documento\Processamento;

use App\Shared\Enums\ResultadoEtapa;
use InvalidArgumentException;

/**
 * Passo de IA a registar (upsert em `extracoes_documento` + `EtapaDocumento`).
 * O passo é sempre o `estado` actual do `Documento`, por isso não é redundado aqui —
 * o `resultado` distingue a tentativa (Sucesso/Falha/EmCurso). Construído
 * programaticamente pelo pipeline (futuro orquestrador, #97/#98) — sem `fromRequest`,
 * VO interno nunca originado de HTTP.
 */
final readonly class RegistarEtapaExtracaoDto
{
    /**
     * @param  ?array{
     *     data_documento?: string,
     *     fornecedor?: array{nif?: string, nome?: string},
     *     cliente?: array{nif?: string, nome?: string},
     *     valor?: float,
     * }  $dadosJson  Shape inferida de `ResultadoExtracaoIA` — o orquestrador real (#97/#98) ainda
     *   não existe; este shape guia a implementação futura, não é um contrato já imposto por código.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
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
