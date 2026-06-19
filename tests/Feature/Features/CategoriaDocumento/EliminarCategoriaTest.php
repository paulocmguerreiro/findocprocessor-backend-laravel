<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('autenticado', function (): void {
    beforeEach(function (): void {
        Sanctum::actingAs(User::factory()->create(), ['api']);
    });

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
});

it('guest sem token recebe 401', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $this->deleteJson("/api/categorias-documento/{$categoria->id}")
        ->assertUnauthorized();
});
