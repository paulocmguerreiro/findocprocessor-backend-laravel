<?php

declare(strict_types=1);

use App\Features\Entidade\EmpresaMae\RegraUnicidadeEmpresaMae;
use App\Models\Entidade;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('não remove marcação quando eEmpresaAplicacao é false', function (): void {
    $marcada = Entidade::factory()->empresaAplicacao()->create();

    app(RegraUnicidadeEmpresaMae::class)->handle(false);

    $this->assertDatabaseHas('entidades', ['id' => $marcada->id, 'e_empresa_aplicacao' => true]);
});

it('remove marcação quando eEmpresaAplicacao é true', function (): void {
    $marcada = Entidade::factory()->empresaAplicacao()->create();

    app(RegraUnicidadeEmpresaMae::class)->handle(true);

    $this->assertDatabaseHas('entidades', ['id' => $marcada->id, 'e_empresa_aplicacao' => false]);
});
