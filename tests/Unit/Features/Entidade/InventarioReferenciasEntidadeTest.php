<?php

declare(strict_types=1);

use App\Features\Entidade\Agrupar\InventarioReferenciasEntidade;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devolve exactamente as colunas conhecidas que referenciam entidades', function (): void {
    $inventario = new InventarioReferenciasEntidade;

    expect($inventario->detectarColunasQueReferenciamEntidades())
        ->toBe(['documentos.id_cliente', 'documentos.id_fornecedor']);
});
