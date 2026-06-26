<?php

declare(strict_types=1);

namespace App\Features\Documento\Transicao;

use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;

/**
 * Invariante de domínio: valida que a transição `De → Para` consta do mapa
 * central de transições permitidas (o grafo da máquina de estados). Rejeita
 * qualquer outra com `TransicaoInvalidaException` (→ 422).
 *
 * Não autoriza nem persiste — é invocada dentro da transação da Action chamante.
 */
final readonly class RegraTransicaoEstado
{
    /**
     * @throws TransicaoInvalidaException
     */
    public function handle(EstadoDocumento $de, EstadoDocumento $para): void
    {
        if (! in_array($para, $this->transicoesPermitidas($de), true)) {
            throw TransicaoInvalidaException::entre($de, $para);
        }
    }

    /**
     * Mapa central De → [Para permitidos]. Espelha o grafo da issue #57.
     *
     * @return list<EstadoDocumento>
     */
    private function transicoesPermitidas(EstadoDocumento $de): array
    {
        return match ($de) {
            EstadoDocumento::Pendente => [EstadoDocumento::AguardaEnvio, EstadoDocumento::Perigoso],
            EstadoDocumento::AguardaEnvio => [EstadoDocumento::Enviado],
            EstadoDocumento::Enviado => [EstadoDocumento::AguardaResposta],
            EstadoDocumento::AguardaResposta => [EstadoDocumento::Processado, EstadoDocumento::Erro, EstadoDocumento::Perigoso],
            EstadoDocumento::Erro => [EstadoDocumento::AguardaEnvio],
            EstadoDocumento::Processado => [EstadoDocumento::Processado],
            EstadoDocumento::Perigoso => [],
        };
    }
}
