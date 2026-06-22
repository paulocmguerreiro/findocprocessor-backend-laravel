<?php

declare(strict_types=1);

use App\Features\Entidade\Criar\CriarEntidadeAction;
use App\Features\Entidade\Criar\CriarEntidadeDto;
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

it('cria entidade com dados válidos', function (): void {
    $dto = new CriarEntidadeDto(
        nome: 'Empresa Teste',
        nif: '501234567',
        eCliente: true,
        eFornecedor: false,
        eEmpresaAplicacao: false,
    );

    $resultado = app(CriarEntidadeAction::class)->handle($dto);

    expect($resultado->nome)->toBe('Empresa Teste')
        ->and($resultado->nif)->toBe('501234567')
        ->and($resultado->e_cliente)->toBeTrue()
        ->and($resultado->e_fornecedor)->toBeFalse()
        ->and($resultado->e_empresa_aplicacao)->toBeFalse();

    $this->assertDatabaseHas('entidades', ['nif' => '501234567']);
});

it('cria como empresa mãe força os três flags e remove marcação anterior', function (): void {
    $anterior = Entidade::factory()->empresaAplicacao()->create();

    $dto = new CriarEntidadeDto(
        nome: 'Nova Empresa Mãe',
        nif: '500000001',
        eCliente: false,
        eFornecedor: false,
        eEmpresaAplicacao: true,
    );

    $resultado = app(CriarEntidadeAction::class)->handle($dto);

    expect($resultado->e_empresa_aplicacao)->toBeTrue()
        ->and($resultado->e_cliente)->toBeTrue()
        ->and($resultado->e_fornecedor)->toBeTrue();

    $this->assertDatabaseHas('entidades', ['id' => $anterior->id, 'e_empresa_aplicacao' => false]);
});

it('faz rollback quando ocorre excepção após insert', function (): void {
    Entidade::created(function (): void {
        throw new RuntimeException('falha simulada após insert');
    });

    $dto = new CriarEntidadeDto(
        nome: 'Empresa Teste',
        nif: '501234567',
        eCliente: true,
        eFornecedor: false,
        eEmpresaAplicacao: false,
    );

    expect(fn (): Entidade => app(CriarEntidadeAction::class)->handle($dto))
        ->toThrow(RuntimeException::class, 'falha simulada após insert');

    $this->assertDatabaseCount('entidades', 0);
});

it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    $this->actingAs($utilizador);

    $dto = new CriarEntidadeDto(nome: 'Empresa', nif: '500000001', eCliente: true, eFornecedor: false, eEmpresaAplicacao: false);

    expect(fn () => app(CriarEntidadeAction::class)->handle($dto))
        ->toThrow(AuthorizationException::class);
});
