<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\TransicoesEstado;

use App\Features\Documento\Operacoes\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Transição `AnaliseTexto|AnaliseOcr → AnaliseIaLocal` (pipeline): o texto está
 * extraído, é "enviado" para o modelo de IA local. Move o ficheiro `entrada →
 * enviado` (RN-02). Não invoca o modelo — só transiciona o estado (RN-06); ligar
 * o cliente de IA é âmbito do orquestrador (#101).
 *
 * Transição de sistema: corre sempre em background (Jobs de extracção), sem
 * utilizador autenticado — não tem `Gate::authorize` (ver `02-shared/padroes-acoes.md`).
 */
final readonly class MarcarAnaliseIaLocalDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento, string $motivo = 'texto extraído, enviado para o modelo local'): Documento
    {
        return $this->executor->executar($documento, EstadoDocumento::AnaliseIaLocal, $motivo);
    }
}
