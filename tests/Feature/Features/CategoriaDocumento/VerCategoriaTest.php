<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

it('devolve categoria existente com estrutura correcta', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $this->getJson("/api/categorias-documento/{$categoria->id}")
        ->assertOk()
        ->assertJson(fn (AssertableJson $json): AssertableJson => $json
            ->has('data', fn (AssertableJson $data): AssertableJson => $data
                ->where('id', $categoria->id)
                ->where('nome', $categoria->nome)
                ->where('slug', $categoria->slug)
                ->where('tipo_movimento', $categoria->tipo_movimento->value)
            )
        );
});

it('devolve 404 quando a categoria não existe', function (): void {
    $this->getJson('/api/categorias-documento/00000000-0000-0000-0000-000000000000')
        ->assertNotFound()
        ->assertJsonPath('status', Response::HTTP_NOT_FOUND)
        ->assertJsonPath('detail', 'Recurso não encontrado.');
});
