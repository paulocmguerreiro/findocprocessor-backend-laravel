<?php

declare(strict_types=1);

namespace App\Features\Documento\RecepcaoUpload;

use DomainException;

/**
 * Upload de um ficheiro cujo conteúdo (hash SHA-256) já existe — previne
 * duplicados. Estende `DomainException` → o handler converte em `422`, evitando
 * que a violação do índice único `hash_sha256` surja como `500`.
 */
final class DocumentoDuplicadoException extends DomainException
{
    public static function paraHash(string $hash): self
    {
        return new self(sprintf('Já existe um documento com o mesmo conteúdo (hash %s…).', substr($hash, 0, 8)));
    }
}
