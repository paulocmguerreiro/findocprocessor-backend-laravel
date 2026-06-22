<?php

declare(strict_types=1);

use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    it('elimina entidade e devolve 204', function (): void {
        $entidade = Entidade::factory()->create();

        $this->deleteJson("/api/entidades/{$entidade->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('entidades', ['id' => $entidade->id]);
    });

    it('devolve 404 quando UUID não existe', function (): void {
        $this->deleteJson('/api/entidades/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $entidade = Entidade::factory()->create();
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    Sanctum::actingAs($utilizador, ['api']);

    $this->deleteJson("/api/entidades/{$entidade->id}")
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $entidade = Entidade::factory()->create();

    $this->deleteJson("/api/entidades/{$entidade->id}")
        ->assertUnauthorized();
});
