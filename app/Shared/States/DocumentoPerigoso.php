<?php

declare(strict_types=1);

namespace App\Shared\States;

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

final readonly class DocumentoPerigoso implements ContratoEstadoDocumento
{
    public function __construct(
        private string $id,
        private string $discoStorage,
        private string $nomeFicheiroStorage,
    ) {}

    public static function deDocumento(Documento $documento): self
    {
        return new self(
            id: $documento->id,
            discoStorage: $documento->disco_storage,
            nomeFicheiroStorage: $documento->nome_ficheiro_storage,
        );
    }

    public function estado(): EstadoDocumento
    {
        return EstadoDocumento::Perigoso;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function discoStorage(): string
    {
        return $this->discoStorage;
    }

    public function nomeFicheiroStorage(): string
    {
        return $this->nomeFicheiroStorage;
    }
}
