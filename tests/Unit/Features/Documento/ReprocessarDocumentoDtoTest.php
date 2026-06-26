<?php

declare(strict_types=1);

use App\Features\Documento\Reprocessar\ModoReprocessamento;
use App\Features\Documento\Reprocessar\ReprocessarDocumentoDto;

it('transporta o modo de reprocessamento', function (ModoReprocessamento $modo): void {
    expect((new ReprocessarDocumentoDto($modo))->modo)->toBe($modo);
})->with([
    'modelo' => [ModoReprocessamento::Modelo],
    'ferramenta' => [ModoReprocessamento::Ferramenta],
]);
