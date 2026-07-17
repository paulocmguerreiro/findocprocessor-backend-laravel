<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\Reprocessar;

use App\Events\DocumentoReprocessadoEvent;
use App\Features\Documento\Operacoes\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Transição `Erro → Pendente` (HTTP). Reabre um documento em erro para
 * reprocessamento, parametrizado pelo `modo`; move o ficheiro `erro → entrada`,
 * regista o `modo` como motivo e emite `DocumentoReprocessadoEvent`.
 *
 * A dimensão de extracção já foi eliminada ao entrar em `Erro`
 * (`RegraEliminarExtracaoTerminal`, RN-03), por isso a Action deixa de precisar
 * de transacção própria ou de reset condicional: delega a atomicidade da
 * transição no `ExecutorTransicaoDocumento` e mantém apenas um `delete()`
 * defensivo idempotente, rede de segurança para o caso raro de existir linha
 * residual (RF-10).
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

        $documentoReaberto = $this->executor->executar(
            $documento,
            EstadoDocumento::Pendente,
            $dados->modo->value,
            evento: fn (Documento $documentoReaberto): DocumentoReprocessadoEvent => new DocumentoReprocessadoEvent($documentoReaberto, $dados->modo),
        );

        // Rede de segurança idempotente: a linha já deveria ter sido eliminada ao
        // entrar em Erro (RN-03) — este delete garante que um documento reaberto
        // nunca herda scratch space de extracção residual.
        ExtracaoDocumento::query()->where('id_documento', $documentoReaberto->id)->delete();

        return $documentoReaberto;
    }
}
