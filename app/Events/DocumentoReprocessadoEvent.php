<?php

declare(strict_types=1);

namespace App\Events;

use App\Features\Documento\Reprocessar\ModoReprocessamento;
use App\Models\Documento;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Documento em `Erro` foi reposto em `Pendente` para reprocessamento,
 * parametrizado pelo `modo`. Disparado após commit.
 */
final class DocumentoReprocessadoEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Documento $documento,
        public ModoReprocessamento $modo,
    ) {}
}
