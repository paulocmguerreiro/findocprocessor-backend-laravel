<?php

declare(strict_types=1);

namespace App\Shared\States;

use App\Shared\Enums\EstadoDocumento;

interface ContratoEstadoDocumento
{
    public function estado(): EstadoDocumento;

    public function id(): string;

    public function discoStorage(): string;

    public function nomeFicheiroStorage(): string;
}
