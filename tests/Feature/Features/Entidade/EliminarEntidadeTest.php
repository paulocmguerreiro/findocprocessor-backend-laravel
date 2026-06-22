<?php

declare(strict_types=1);

use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('autenticado', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

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
    criarEAutenticarUtilizador();

    $this->deleteJson("/api/entidades/{$entidade->id}")
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $entidade = Entidade::factory()->create();

    $this->deleteJson("/api/entidades/{$entidade->id}")
        ->assertUnauthorized();
});
