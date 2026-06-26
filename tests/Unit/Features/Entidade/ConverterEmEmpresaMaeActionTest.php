<?php

declare(strict_types=1);

use App\Features\Entidade\EmpresaMae\ConverterEmEmpresaMaeAction;
use App\Models\Entidade;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['entidades'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

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
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $entidade = Entidade::factory()->create();

        expect(fn () => app(ConverterEmEmpresaMaeAction::class)->handle($entidade))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $entidade = Entidade::factory()->create();

    expect(fn () => app(ConverterEmEmpresaMaeAction::class)->handle($entidade))
        ->toThrow(AuthorizationException::class);
});
