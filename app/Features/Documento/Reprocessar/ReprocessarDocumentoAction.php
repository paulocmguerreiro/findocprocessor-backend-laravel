<?php

declare(strict_types=1);

namespace App\Features\Documento\Reprocessar;

use App\Events\DocumentoReprocessado;
use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Transição `Erro → AguardaEnvio` (HTTP). Reabre um documento em erro para
 * reprocessamento, parametrizado pelo `modo`; move o ficheiro `erro → entrada`,
 * regista o `modo` como motivo e emite `DocumentoReprocessado`.
 */
final readonly class ReprocessarDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Documento $documento, ReprocessarDocumentoDto $dados): Documento
    {
        Gate::authorize('update', $documento);

        return $this->executor->executar(
            $documento,
            EstadoDocumento::AguardaEnvio,
            $dados->modo->value,
            evento: fn (Documento $documentoReaberto): DocumentoReprocessado => new DocumentoReprocessado($documentoReaberto, $dados->modo),
        );
    }
}
