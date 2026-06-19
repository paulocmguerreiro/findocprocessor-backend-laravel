<?php

declare(strict_types=1);

use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('autenticado', function (): void {
    beforeEach(function (): void {
        Sanctum::actingAs(User::factory()->create(), ['api']);
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

it('guest sem token recebe 401', function (): void {
    $entidade = Entidade::factory()->create();

    $this->deleteJson("/api/entidades/{$entidade->id}")
        ->assertUnauthorized();
});
