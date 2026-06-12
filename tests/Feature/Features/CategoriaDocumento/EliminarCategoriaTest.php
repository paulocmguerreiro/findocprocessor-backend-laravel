<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

uses(RefreshDatabase::class);

it('elimina categoria existente e devolve 204', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $this->deleteJson("/api/categorias-documento/{$categoria->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('categorias_documento', ['id' => $categoria->id]);
});

it('devolve 404 quando a categoria não existe', function (): void {
    $this->deleteJson('/api/categorias-documento/00000000-0000-0000-0000-000000000000')
        ->assertNotFound()
        ->assertJsonPath('status', Response::HTTP_NOT_FOUND)
        ->assertJsonPath('detail', 'Recurso não encontrado.');
});
