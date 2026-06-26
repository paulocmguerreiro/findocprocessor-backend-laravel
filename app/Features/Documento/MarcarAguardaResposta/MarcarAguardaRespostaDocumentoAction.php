<?php

declare(strict_types=1);

namespace App\Features\Documento\MarcarAguardaResposta;

use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Transição `Enviado → AguardaResposta` (pipeline). Ficheiro fica no disco `enviado`.
 *
 * Transição de sistema: corre sempre em background (Jobs de extracção), sem
 * utilizador autenticado — não tem `Gate::authorize` (ver `02-shared/padroes-acoes.md`).
 */
final readonly class MarcarAguardaRespostaDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento): Documento
    {
        return $this->executor->executar($documento, EstadoDocumento::AguardaResposta, 'a aguardar resposta da extracção');
    }
}
