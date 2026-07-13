<?php

declare(strict_types=1);

namespace App\Features\Documento\MarcarAguardaEnvio;

use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Transição `Pendente → AguardaEnvio` (pipeline). Ficheiro fica no disco `entrada`.
 *
 * Transição de sistema: corre sempre em background (Jobs de extracção), sem
 * utilizador autenticado — não tem `Gate::authorize` (ver `02-shared/padroes-acoes.md`).
 */
final readonly class MarcarAguardaEnvioDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento, string $motivo = 'pronto para envio'): Documento
    {
        return $this->executor->executar($documento, EstadoDocumento::AguardaEnvio, $motivo);
    }
}
