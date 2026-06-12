<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Ver\VerCategoriaAction;
use App\Models\CategoriaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devolve o modelo quando recebe CategoriaDocumento directamente', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $resultado = (new VerCategoriaAction)->handle($categoria);

    expect($resultado)->toBe($categoria);
});

it('resolve o modelo quando recebe string UUID', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $resultado = (new VerCategoriaAction)->handle($categoria->id);

    expect($resultado->id)->toBe($categoria->id);
});
