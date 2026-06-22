<?php

declare(strict_types=1);

use App\Features\Entidade\EmpresaMae\ConverterEmEmpresaMaeAction;
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

it('converte quando recebe Entidade directamente', function (): void {
    $entidade = Entidade::factory()->create([
        'e_cliente' => false,
        'e_fornecedor' => false,
        'e_empresa_aplicacao' => false,
    ]);

    $resultado = app(ConverterEmEmpresaMaeAction::class)->handle($entidade);

    expect($resultado->e_empresa_aplicacao)->toBeTrue()
        ->and($resultado->e_cliente)->toBeTrue()
        ->and($resultado->e_fornecedor)->toBeTrue();
});

it('converte quando recebe string UUID', function (): void {
    $entidade = Entidade::factory()->create();

    $resultado = app(ConverterEmEmpresaMaeAction::class)->handle($entidade->id);

    expect($resultado->e_empresa_aplicacao)->toBeTrue();
});

it('remove a marcação da empresa mãe anterior ao converter uma nova', function (): void {
    $anterior = Entidade::factory()->empresaAplicacao()->create();
    $nova = Entidade::factory()->create();

    app(ConverterEmEmpresaMaeAction::class)->handle($nova);

    $this->assertDatabaseHas('entidades', ['id' => $anterior->id, 'e_empresa_aplicacao' => false]);
    $this->assertDatabaseHas('entidades', ['id' => $nova->id, 'e_empresa_aplicacao' => true]);
});

it('faz rollback quando ocorre excepção durante conversão', function (): void {
    $entidade = Entidade::factory()->create(['e_empresa_aplicacao' => false]);

    Entidade::saved(function (): void {
        throw new RuntimeException('falha simulada durante conversão');
    });

    expect(fn (): Entidade => app(ConverterEmEmpresaMaeAction::class)->handle($entidade))
        ->toThrow(RuntimeException::class, 'falha simulada durante conversão');

    $this->assertDatabaseHas('entidades', ['id' => $entidade->id, 'e_empresa_aplicacao' => false]);
});

it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
    $entidade = Entidade::factory()->create();
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    $this->actingAs($utilizador);

    expect(fn () => app(ConverterEmEmpresaMaeAction::class)->handle($entidade))
        ->toThrow(AuthorizationException::class);
});
