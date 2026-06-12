<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Criar\CriarCategoriaDto;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaRequest;
use App\Shared\Enums\TipoMovimento;

it('lança UnexpectedValueException se validated() devolver tipos não-string', function (): void {
    $request = Mockery::mock(CriarCategoriaRequest::class);
    $request->shouldReceive('validated')->andReturn([
        'nome' => 123,
        'slug' => 'slug-valido',
        'tipo_movimento' => TipoMovimento::Debito->value,
    ]);

    expect(fn (): CriarCategoriaDto => CriarCategoriaDto::fromRequest($request))
        ->toThrow(UnexpectedValueException::class);
});
