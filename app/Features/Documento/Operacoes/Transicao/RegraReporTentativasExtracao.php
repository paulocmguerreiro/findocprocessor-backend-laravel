<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\Transicao;

use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Invariante de domínio (RN-05/RF-13, #111): `extracao_tentativas` conta as falhas
 * técnicas da **etapa actual** e cada etapa tem direito a um orçamento próprio de
 * tentativas. Por isso, sempre que o documento avança correctamente para um estado
 * **não-terminal** (o pipeline continua), o contador é reposto a 0 — a nova etapa
 * arranca com o orçamento cheio. Numa transição para `Erro` o contador **nunca** é
 * reposto; nos terminais `Processado`/`Perigoso` a linha é eliminada de qualquer
 * forma (`RegraEliminarExtracaoTerminal`), pelo que a reposição não se aplica.
 *
 * Invocada dentro da transacção de `ExecutorTransicaoDocumento`. No-op quando não
 * existe `ExtracaoDocumento` (update de 0 linhas não é erro).
 */
final readonly class RegraReporTentativasExtracao
{
    public function handle(Documento $documento, EstadoDocumento $novoEstado): void
    {
        if (! $this->deveReporContador($novoEstado)) {
            return;
        }

        ExtracaoDocumento::query()
            ->where('id_documento', $documento->id)
            ->update(['extracao_tentativas' => 0]);
    }

    /**
     * Estados não-terminais em que o pipeline prossegue (match exaustivo, sem
     * `default`): um estado novo obriga a decidir explicitamente se repõe o contador.
     */
    private function deveReporContador(EstadoDocumento $estado): bool
    {
        return match ($estado) {
            EstadoDocumento::Pendente,
            EstadoDocumento::AnaliseMalware,
            EstadoDocumento::AnaliseTexto,
            EstadoDocumento::AnaliseOcr,
            EstadoDocumento::AnaliseIaLocal,
            EstadoDocumento::AnaliseCloud => true,
            EstadoDocumento::Processado,
            EstadoDocumento::Erro,
            EstadoDocumento::Perigoso => false,
        };
    }
}
