<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaAction;
use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaDto;
use App\Models\CategoriaDocumento;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('actualiza quando recebe CategoriaDocumento directamente', function (): void {
    $categoria = CategoriaDocumento::factory()->comMovimentoDebito()->create(['nome' => 'Original']);

    $dto = new ActualizarCategoriaDto(nome: 'Actualizado', slug: null, tipoMovimento: null);
    $resultado = (new ActualizarCategoriaAction)->handle($categoria, $dto);

    expect($resultado->nome)->toBe('Actualizado')
        ->and($resultado->tipo_movimento)->toBe(TipoMovimento::Debito);
});

it('actualiza quando recebe string UUID', function (): void {
    $categoria = CategoriaDocumento::factory()->comMovimentoCredito()->create(['nome' => 'Original']);

    $dto = new ActualizarCategoriaDto(nome: 'Actualizado', slug: null, tipoMovimento: null);
    $resultado = (new ActualizarCategoriaAction)->handle($categoria->id, $dto);

    expect($resultado->nome)->toBe('Actualizado')
        ->and($resultado->tipo_movimento)->toBe(TipoMovimento::Credito);
});
