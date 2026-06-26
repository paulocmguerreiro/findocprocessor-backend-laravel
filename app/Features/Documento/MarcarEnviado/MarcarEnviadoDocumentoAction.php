<?php

declare(strict_types=1);

namespace App\Features\Documento\MarcarEnviado;

use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Transição `AguardaEnvio → Enviado` (pipeline). Move o ficheiro `entrada → enviado`.
 */
final readonly class MarcarEnviadoDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Documento $documento): Documento
    {
        Gate::authorize('update', $documento);

        return $this->executor->executar($documento, EstadoDocumento::Enviado, 'enviado para extracção');
    }
}
