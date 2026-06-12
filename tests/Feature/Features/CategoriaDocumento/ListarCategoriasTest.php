<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

it('devolve lista vazia quando não existem categorias', function (): void {
    $this->getJson('/api/categorias-documento')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json): AssertableJson => $json
            ->where('data', [])
            ->where('meta.total', 0)
        );
});

it('devolve lista de categorias com estrutura correcta', function (): void {
    CategoriaDocumento::factory()->count(3)->create();

    $this->getJson('/api/categorias-documento')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonStructure([
            'data' => [['id', 'nome', 'slug', 'tipo_movimento']],
            'meta' => ['total'],
        ]);
});
