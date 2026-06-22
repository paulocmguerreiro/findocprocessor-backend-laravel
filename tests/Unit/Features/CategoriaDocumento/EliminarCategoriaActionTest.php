<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Eliminar\EliminarCategoriaAction;
use App\Models\CategoriaDocumento;
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

it('elimina quando recebe CategoriaDocumento directamente', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    (new EliminarCategoriaAction)->handle($categoria);

    $this->assertDatabaseMissing('categorias_documento', ['id' => $categoria->id]);
});

it('elimina quando recebe string UUID', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    (new EliminarCategoriaAction)->handle($categoria->id);

    $this->assertDatabaseMissing('categorias_documento', ['id' => $categoria->id]);
});

it('faz rollback quando ocorre excepção durante eliminação', function (): void {
    $categoria = CategoriaDocumento::factory()->create();

    CategoriaDocumento::deleting(function (): void {
        throw new RuntimeException('falha simulada durante eliminação');
    });

    expect(fn () => (new EliminarCategoriaAction)->handle($categoria))
        ->toThrow(RuntimeException::class, 'falha simulada durante eliminação');

    $this->assertDatabaseHas('categorias_documento', ['id' => $categoria->id]);
});

it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    $this->actingAs($utilizador);

    expect(fn () => (new EliminarCategoriaAction)->handle($categoria))
        ->toThrow(AuthorizationException::class);
});
