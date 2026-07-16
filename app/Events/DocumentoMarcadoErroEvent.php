<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Documento;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Documento transitou para `Erro` durante o pipeline. Disparado após commit.
 */
final class DocumentoMarcadoErroEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Documento $documento,
        public string $mensagemErro,
    ) {}
}
