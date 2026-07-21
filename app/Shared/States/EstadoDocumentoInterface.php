<?php

declare(strict_types=1);

namespace App\Shared\States;

use App\Shared\Enums\EstadoDocumento;

interface EstadoDocumentoInterface
{
    public function obterEstado(): EstadoDocumento;

    public function obterId(): string;

    public function obterDiscoStorage(): string;

    public function obterNomeFicheiroStorage(): string;
}
