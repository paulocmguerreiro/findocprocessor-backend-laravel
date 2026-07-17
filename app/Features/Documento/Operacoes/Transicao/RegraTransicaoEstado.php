<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\Transicao;

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
     * Mapa central De → [Para permitidos]. Espelha o `stateDiagram-v2` da máquina
     * de estados unificada (issue #110): o pipeline de extracção corre localmente
     * (`Pendente → AnaliseMalware → AnaliseTexto/AnaliseOcr → AnaliseIaLocal →
     * AnaliseCloud → Processado/Erro/Perigoso`), pelo que cada passo de análise é
     * um estado próprio. `Erro → Pendente` reabre o pipeline (reprocessamento);
     * `Processado → Processado` é o self-loop de correcção.
     *
     * @return list<EstadoDocumento>
     */
    private function transicoesPermitidas(EstadoDocumento $de): array
    {
        return match ($de) {
            EstadoDocumento::Pendente => [EstadoDocumento::AnaliseMalware],
            EstadoDocumento::AnaliseMalware => [EstadoDocumento::AnaliseTexto, EstadoDocumento::Perigoso, EstadoDocumento::Erro],
            EstadoDocumento::AnaliseTexto => [EstadoDocumento::AnaliseIaLocal, EstadoDocumento::AnaliseOcr, EstadoDocumento::Erro],
            EstadoDocumento::AnaliseOcr => [EstadoDocumento::AnaliseIaLocal, EstadoDocumento::Erro],
            EstadoDocumento::AnaliseIaLocal => [EstadoDocumento::Processado, EstadoDocumento::AnaliseCloud, EstadoDocumento::Perigoso, EstadoDocumento::Erro],
            EstadoDocumento::AnaliseCloud => [EstadoDocumento::Processado, EstadoDocumento::Erro, EstadoDocumento::Perigoso],
            EstadoDocumento::Erro => [EstadoDocumento::Pendente],
            EstadoDocumento::Processado => [EstadoDocumento::Processado],
            EstadoDocumento::Perigoso => [],
        };
    }
}
