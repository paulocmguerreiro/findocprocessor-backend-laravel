<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Eliminar\EliminarCategoriaAction;
use App\Models\CategoriaDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

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
