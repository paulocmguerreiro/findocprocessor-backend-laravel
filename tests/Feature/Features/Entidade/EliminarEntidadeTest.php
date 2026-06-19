<?php

declare(strict_types=1);

use App\Features\Entidade\Eliminar\EliminarEntidadeAction;
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

it('elimina entidade a partir de UUID string directamente na action', function (): void {
    $entidade = Entidade::factory()->create();

    app(EliminarEntidadeAction::class)->handle($entidade->id);

    $this->assertDatabaseMissing('entidades', ['id' => $entidade->id]);
});
