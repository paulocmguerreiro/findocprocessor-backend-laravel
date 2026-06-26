<?php

declare(strict_types=1);

namespace App\Features\Documento\RecepcaoUpload;

use Illuminate\Http\UploadedFile;

/**
 * Ficheiro recebido por upload, a registar em `Pendente`. A Action calcula o
 * `hash_sha256` e escreve no disco `entrada`.
 */
final readonly class ReceberUploadDocumentoDto
{
    public function __construct(public UploadedFile $ficheiro) {}

    public static function fromRequest(ReceberUploadDocumentoRequest $request): self
    {
        /** @var UploadedFile $ficheiro */
        $ficheiro = $request->file('ficheiro');

        return new self($ficheiro);
    }
}
