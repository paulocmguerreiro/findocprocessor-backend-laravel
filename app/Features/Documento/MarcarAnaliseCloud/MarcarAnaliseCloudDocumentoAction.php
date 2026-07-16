<?php

declare(strict_types=1);

namespace App\Features\Documento\MarcarAnaliseCloud;

use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Transição `AnaliseIaLocal → AnaliseCloud` (pipeline): o modelo local não chegou,
 * o documento é escalado para o modelo de IA cloud. Ficheiro fica no disco `enviado`.
 * Não invoca o modelo — só transiciona o estado (RN-06); ligar o cliente de IA é
 * âmbito do orquestrador (#101).
 *
 * Transição de sistema: corre sempre em background (Jobs de extracção), sem
 * utilizador autenticado — não tem `Gate::authorize` (ver `02-shared/padroes-acoes.md`).
 */
final readonly class MarcarAnaliseCloudDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento, string $motivo = 'escalado para o modelo cloud'): Documento
    {
        return $this->executor->executar($documento, EstadoDocumento::AnaliseCloud, $motivo);
    }
}
