<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Ver\VerCategoriaAction;
use App\Models\CategoriaDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

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
