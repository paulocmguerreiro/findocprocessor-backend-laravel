<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Eliminar\EliminarCategoriaAction;
use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('CA-13: faz soft delete da categoria quando tem um tipo de documento associado', function (): void {
    $this->actingAs(criarAdmin());

    $categoria = CategoriaDocumento::factory()->create();
    TipoDocumento::factory()->for($categoria, 'categoria')->create();

    app(EliminarCategoriaAction::class)->handle($categoria);

    $this->assertSoftDeleted('categorias_documento', ['id' => $categoria->id]);
});
