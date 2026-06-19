<?php

declare(strict_types=1);

use App\Models\Entidade;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
