<?php

declare(strict_types=1);

use App\Features\Entidade\Eliminar\EliminarEntidadeAction;
use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('elimina quando recebe Entidade directamente', function (): void {
    $entidade = Entidade::factory()->create();

    (new EliminarEntidadeAction)->handle($entidade);

    $this->assertDatabaseMissing('entidades', ['id' => $entidade->id]);
});

it('elimina quando recebe string UUID', function (): void {
    $entidade = Entidade::factory()->create();

    (new EliminarEntidadeAction)->handle($entidade->id);

    $this->assertDatabaseMissing('entidades', ['id' => $entidade->id]);
});

it('faz rollback quando ocorre excepção durante eliminação', function (): void {
    $entidade = Entidade::factory()->create();

    Entidade::deleting(function (): void {
        throw new RuntimeException('falha simulada durante eliminação');
    });

    expect(fn () => (new EliminarEntidadeAction)->handle($entidade))
        ->toThrow(RuntimeException::class, 'falha simulada durante eliminação');

    $this->assertDatabaseHas('entidades', ['id' => $entidade->id]);
});
