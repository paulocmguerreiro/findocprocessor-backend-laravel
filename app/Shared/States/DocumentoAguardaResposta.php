<?php

declare(strict_types=1);

namespace App\Shared\States;

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

final readonly class DocumentoAguardaResposta implements ContratoEstadoDocumento
{
    public function __construct(
        private string $id,
        private string $discoStorage,
        private string $nomeFicheiroStorage,
        private string $nomeFicheiroOriginal,
        private string $hashSha256,
    ) {}

    public static function deDocumento(Documento $documento): self
    {
        return new self(
            id: $documento->id,
            discoStorage: $documento->disco_storage,
            nomeFicheiroStorage: $documento->nome_ficheiro_storage,
            nomeFicheiroOriginal: $documento->nome_ficheiro_original,
            hashSha256: $documento->hash_sha256,
        );
    }

    public function estado(): EstadoDocumento
    {
        return EstadoDocumento::AguardaResposta;
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

    public function nomeFicheiroOriginal(): string
    {
        return $this->nomeFicheiroOriginal;
    }

    public function hashSha256(): string
    {
        return $this->hashSha256;
    }
}
