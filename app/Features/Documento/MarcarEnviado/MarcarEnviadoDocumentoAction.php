<?php

declare(strict_types=1);

namespace App\Features\Documento\MarcarEnviado;

use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Transição `AguardaEnvio → Enviado` (pipeline). Move o ficheiro `entrada → enviado`.
 *
 * Transição de sistema: corre sempre em background (Jobs de extracção), sem
 * utilizador autenticado — não tem `Gate::authorize` (ver `02-shared/padroes-acoes.md`).
 */
final readonly class MarcarEnviadoDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento): Documento
    {
        return $this->executor->executar($documento, EstadoDocumento::Enviado, 'enviado para extracção');
    }
}
