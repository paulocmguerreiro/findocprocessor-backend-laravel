<?php

declare(strict_types=1);

namespace App\Features\Documento\MarcarPerigoso;

use App\Events\DocumentoMarcadoPerigoso;
use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Transição para `Perigoso` (pipeline) — alcançável de `Pendente` (pré-scan) e de
 * `AguardaResposta` (guardrail). Move o ficheiro para o disco `perigoso`, regista
 * o motivo e emite `DocumentoMarcadoPerigoso`.
 *
 * Transição de sistema: corre sempre em background (Jobs de extracção), sem
 * utilizador autenticado — não tem `Gate::authorize` (ver `02-shared/padroes-acoes.md`).
 */
final readonly class MarcarPerigosoDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento, MarcarPerigosoDocumentoDto $dados): Documento
    {
        return $this->executor->executar(
            $documento,
            EstadoDocumento::Perigoso,
            $dados->motivo,
            evento: fn (Documento $documentoPerigoso): DocumentoMarcadoPerigoso => new DocumentoMarcadoPerigoso($documentoPerigoso, $dados->motivo),
        );
    }
}
