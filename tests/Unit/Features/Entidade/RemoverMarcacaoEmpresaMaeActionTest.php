<?php

declare(strict_types=1);

use App\Features\Entidade\EmpresaMae\RemoverMarcacaoEmpresaMaeAction;
use App\Models\Entidade;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('remove a marcação de empresa mãe de todas as entidades marcadas', function (): void {
    $marcada = Entidade::factory()->empresaAplicacao()->create();
    $outra = Entidade::factory()->cliente()->create();

    (new RemoverMarcacaoEmpresaMaeAction)->handle();

    $this->assertDatabaseHas('entidades', ['id' => $marcada->id, 'e_empresa_aplicacao' => false]);
    $this->assertDatabaseHas('entidades', ['id' => $outra->id, 'e_cliente' => true]);
});

it('não afecta entidades sem marcação de empresa mãe', function (): void {
    $entidade = Entidade::factory()->clienteEFornecedor()->create();

    (new RemoverMarcacaoEmpresaMaeAction)->handle();

    $this->assertDatabaseHas('entidades', [
        'id' => $entidade->id,
        'e_cliente' => true,
        'e_fornecedor' => true,
        'e_empresa_aplicacao' => false,
    ]);
});
