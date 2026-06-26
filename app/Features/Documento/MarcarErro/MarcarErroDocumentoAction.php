<?php

declare(strict_types=1);

namespace App\Features\Documento\MarcarErro;

use App\Events\DocumentoMarcadoErro;
use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Transição `AguardaResposta → Erro` (pipeline). Move o ficheiro `enviado → erro`,
 * regista a `mensagem_erro` como motivo e emite `DocumentoMarcadoErro`.
 */
final readonly class MarcarErroDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Documento $documento, MarcarErroDocumentoDto $dados): Documento
    {
        Gate::authorize('update', $documento);

        return $this->executor->executar(
            $documento,
            EstadoDocumento::Erro,
            $dados->mensagemErro,
            evento: fn (Documento $documentoComErro): DocumentoMarcadoErro => new DocumentoMarcadoErro($documentoComErro, $dados->mensagemErro),
        );
    }
}
