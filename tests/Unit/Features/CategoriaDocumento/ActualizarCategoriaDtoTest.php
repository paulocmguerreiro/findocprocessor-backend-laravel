<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaDto;
use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest;

it('lança UnexpectedValueException se validated() devolver tipos não-string', function (): void {
    $request = Mockery::mock(ActualizarCategoriaRequest::class);
    $request->shouldReceive('validated')->andReturn([
        'nome' => 123,
    ]);

    expect(fn (): ActualizarCategoriaDto => ActualizarCategoriaDto::fromRequest($request))
        ->toThrow(UnexpectedValueException::class);
});
