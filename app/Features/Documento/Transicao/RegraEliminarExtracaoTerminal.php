<?php

declare(strict_types=1);

namespace App\Features\Documento\Transicao;

use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Invariante de domínio (RGPD, RN-03/RF-09, #110): `extracoes_documento` é o
 * scratch space do pipeline de extracção — só faz sentido enquanto o documento
 * está a ser processado. Ao entrar num estado **terminal**
 * (`Processado`/`Erro`/`Perigoso`), a linha (incl. a PII `texto_extraido`/
 * `dados_json`) deixa de ter função e é eliminada; nos restantes estados, não faz
 * nada. Invocada dentro da transacção de `ExecutorTransicaoDocumento`.
 *
 * "Terminal" aqui é a noção de fim do pipeline de extracção, não de estado sem
 * saída no grafo — `Erro` tem aresta `Erro → Pendente` (reprocessar) mas é na
 * mesma terminal para a extracção. Por isso não se deriva de `RegraTransicaoEstado`.
 *
 * Não autoriza nem valida transições — só limpa o scratch space quando aplicável.
 */
final readonly class RegraEliminarExtracaoTerminal
{
    public function handle(Documento $documento, EstadoDocumento $novoEstado): void
    {
        if (! $this->eEstadoTerminal($novoEstado)) {
            return;
        }

        // Sem efeito quando não existe linha (delete de 0 linhas não é erro).
        ExtracaoDocumento::query()->where('id_documento', $documento->id)->delete();
    }

    /**
     * Estados que encerram o pipeline de extracção (match exaustivo, sem `default`):
     * um estado novo obriga a decidir explicitamente se descarta a extracção.
     */
    private function eEstadoTerminal(EstadoDocumento $estado): bool
    {
        return match ($estado) {
            EstadoDocumento::Processado, EstadoDocumento::Erro, EstadoDocumento::Perigoso => true,
            EstadoDocumento::Pendente,
            EstadoDocumento::AnaliseMalware,
            EstadoDocumento::AnaliseTexto,
            EstadoDocumento::AnaliseOcr,
            EstadoDocumento::AnaliseIaLocal,
            EstadoDocumento::AnaliseCloud => false,
        };
    }
}
