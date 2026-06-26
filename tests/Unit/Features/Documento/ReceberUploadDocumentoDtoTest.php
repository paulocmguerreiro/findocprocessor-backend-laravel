<?php

declare(strict_types=1);

use App\Features\Documento\RecepcaoUpload\ReceberUploadDocumentoDto;
use Illuminate\Http\UploadedFile;

it('transporta o ficheiro recebido', function (): void {
    $ficheiro = UploadedFile::fake()->create('fatura.pdf', 100);

    expect((new ReceberUploadDocumentoDto($ficheiro))->ficheiro)->toBe($ficheiro);
});
