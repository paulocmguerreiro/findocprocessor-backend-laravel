<?php

declare(strict_types=1);

namespace App\Features\Documento\MarcarAnaliseTexto;

use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Transição `AnaliseMalware → AnaliseTexto` (pipeline): o scan de malware passou,
 * o documento segue para extracção de texto. Ficheiro fica no disco `entrada`.
 * Não invoca nenhum motor — só transiciona o estado (RN-06).
 *
 * Transição de sistema: corre sempre em background (Jobs de extracção), sem
 * utilizador autenticado — não tem `Gate::authorize` (ver `02-shared/padroes-acoes.md`).
 */
final readonly class MarcarAnaliseTextoDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento, string $motivo = 'análise de malware concluída'): Documento
    {
        return $this->executor->executar($documento, EstadoDocumento::AnaliseTexto, $motivo);
    }
}
