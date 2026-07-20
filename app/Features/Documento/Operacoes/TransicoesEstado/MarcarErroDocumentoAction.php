<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\TransicoesEstado;

use App\Events\DocumentoMarcadoErroEvent;
use App\Features\Documento\Operacoes\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Transição para `Erro` (pipeline) — alcançável de qualquer estado de análise
 * (`AnaliseMalware`, `AnaliseTexto`, `AnaliseOcr`, `AnaliseIaLocal`, `AnaliseCloud`)
 * quando o passo respectivo falha. Move o ficheiro para o disco `erro`, regista a
 * `mensagem_erro` como motivo e emite `DocumentoMarcadoErroEvent`.
 *
 * Transição de sistema: corre sempre em background (Jobs de extracção), sem
 * utilizador autenticado — não tem `Gate::authorize` (ver `02-shared/padroes-acoes.md`).
 */
final readonly class MarcarErroDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento, MarcarErroDocumentoDto $dados): Documento
    {
        return $this->executor->executar(
            $documento,
            EstadoDocumento::Erro,
            $dados->mensagemErro,
            evento: fn (Documento $documentoComErro): DocumentoMarcadoErroEvent => new DocumentoMarcadoErroEvent($documentoComErro, $dados->mensagemErro),
        );
    }
}
