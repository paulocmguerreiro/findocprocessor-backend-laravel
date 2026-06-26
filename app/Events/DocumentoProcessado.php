<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Documento;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Documento atingiu o estado `Processado` (registo manual ou fim do pipeline).
 * Disparado após o commit da transação da Action. Sem listeners nesta issue.
 */
final class DocumentoProcessado implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Documento $documento) {}
}
