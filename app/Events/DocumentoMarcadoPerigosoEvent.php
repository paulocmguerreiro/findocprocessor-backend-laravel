<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Documento;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Documento marcado como `Perigoso` (pré-scan ou guardrail). Disparado após commit.
 */
final class DocumentoMarcadoPerigosoEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Documento $documento,
        public string $motivo,
    ) {}
}
