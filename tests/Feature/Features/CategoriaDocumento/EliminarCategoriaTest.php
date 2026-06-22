<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

describe('autenticado', function (): void {
    beforeEach(function (): void {
        $utilizador = User::factory()->create();
        $utilizador->assignRole('admin');
        Sanctum::actingAs($utilizador, ['api']);
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

it('utilizador sem permissão recebe 403', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    Sanctum::actingAs($utilizador, ['api']);

    $this->deleteJson("/api/categorias-documento/{$categoria->id}")
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    $this->deleteJson("/api/categorias-documento/{$categoria->id}")
        ->assertUnauthorized();
});
