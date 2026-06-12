<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

it('actualiza campos enviados e devolve 200 com o recurso', function (): void {
    $categoria = CategoriaDocumento::factory()->comMovimentoDebito()->create(['nome' => 'Nome Original']);

    $this->putJson("/api/categorias-documento/{$categoria->id}", ['nome' => 'Nome Actualizado'])
        ->assertOk()
        ->assertJson(fn (AssertableJson $json): AssertableJson => $json
            ->has('data', fn (AssertableJson $data): AssertableJson => $data
                ->where('id', $categoria->id)
                ->where('nome', 'Nome Actualizado')
                ->where('slug', $categoria->slug)
                ->where('tipo_movimento', TipoMovimento::Debito->value)
            )
        );

    $this->assertDatabaseHas('categorias_documento', ['id' => $categoria->id, 'nome' => 'Nome Actualizado']);
});

it('actualiza tipo_movimento isoladamente', function (): void {
    $categoria = CategoriaDocumento::factory()->comMovimentoDebito()->create();

    $this->putJson("/api/categorias-documento/{$categoria->id}", [
        'tipo_movimento' => TipoMovimento::Credito->value,
    ])
        ->assertOk()
        ->assertJsonPath('data.tipo_movimento', TipoMovimento::Credito->value);
});

it('devolve 404 quando a categoria não existe', function (): void {
    $this->putJson('/api/categorias-documento/00000000-0000-0000-0000-000000000000', ['nome' => 'X'])
        ->assertNotFound()
        ->assertJsonPath('status', Response::HTTP_NOT_FOUND)
        ->assertJsonPath('detail', 'Recurso não encontrado.');
});

it('devolve 422 quando o slug já pertence a outra categoria', function (): void {
    CategoriaDocumento::factory()->create(['slug' => 'slug-existente']);
    $categoria = CategoriaDocumento::factory()->create(['slug' => 'outro-slug']);

    $this->putJson("/api/categorias-documento/{$categoria->id}", ['slug' => 'slug-existente'])
        ->assertUnprocessable()
        ->assertJsonPath('status', Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonStructure(['status', 'detail', 'errors' => ['slug']]);
});

it('permite actualizar o slug da própria categoria sem erro de unicidade', function (): void {
    $categoria = CategoriaDocumento::factory()->create(['slug' => 'slug-proprio']);

    $this->putJson("/api/categorias-documento/{$categoria->id}", ['slug' => 'slug-proprio'])
        ->assertOk();
});
