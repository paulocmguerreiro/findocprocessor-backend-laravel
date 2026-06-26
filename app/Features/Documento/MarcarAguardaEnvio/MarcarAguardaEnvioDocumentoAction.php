<?php

declare(strict_types=1);

namespace App\Features\Documento\MarcarAguardaEnvio;

use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Transição `Pendente → AguardaEnvio` (pipeline). Ficheiro fica no disco `entrada`.
 */
final readonly class MarcarAguardaEnvioDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Documento $documento): Documento
    {
        Gate::authorize('update', $documento);

        return $this->executor->executar($documento, EstadoDocumento::AguardaEnvio, 'pronto para envio');
    }
}
