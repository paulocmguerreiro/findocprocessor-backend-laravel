<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\TransicoesEstado;

use App\Features\Documento\Operacoes\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Transição `AnaliseTexto → AnaliseOcr` (pipeline): o texto nativo é insuficiente,
 * o documento segue para OCR. Ficheiro fica no disco `entrada`. Não invoca o motor
 * de OCR — só transiciona o estado (RN-06); ligar o motor é âmbito do orquestrador (#101).
 *
 * Transição de sistema: corre sempre em background (Jobs de extracção), sem
 * utilizador autenticado — não tem `Gate::authorize` (ver `02-shared/padroes-acoes.md`).
 */
final readonly class MarcarAnaliseOcrDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento, string $motivo = 'texto ilegível, encaminhado para OCR'): Documento
    {
        return $this->executor->executar($documento, EstadoDocumento::AnaliseOcr, $motivo);
    }
}
