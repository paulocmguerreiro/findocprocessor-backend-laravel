<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Eliminar\EliminarCategoriaAction;
use App\Models\CategoriaDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
