<?php

declare(strict_types=1);

use App\Features\Entidade\Eliminar\EliminarEntidadeAction;
use App\Models\Entidade;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $utilizador = User::factory()->create();
    $utilizador->assignRole('admin');
    $this->actingAs($utilizador);
});

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

it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
    $entidade = Entidade::factory()->create();
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    $this->actingAs($utilizador);

    expect(fn () => (new EliminarEntidadeAction)->handle($entidade))
        ->toThrow(AuthorizationException::class);
});
