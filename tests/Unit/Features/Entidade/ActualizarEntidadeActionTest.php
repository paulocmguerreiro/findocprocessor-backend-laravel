<?php

declare(strict_types=1);

use App\Features\Entidade\Actualizar\ActualizarEntidadeAction;
use App\Features\Entidade\Actualizar\ActualizarEntidadeDto;
use App\Models\Entidade;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('actualiza quando recebe Entidade directamente', function (): void {
        $entidade = Entidade::factory()->create(['nome' => 'Original', 'nif' => '111111111']);

        $dto = new ActualizarEntidadeDto(
            nome: 'Actualizado',
            nif: '222222222',
            eCliente: true,
            eFornecedor: false,
            eEmpresaAplicacao: false,
        );

        $resultado = app(ActualizarEntidadeAction::class)->handle($entidade, $dto);

        expect($resultado->nome)->toBe('Actualizado')
            ->and($resultado->nif)->toBe('222222222')
            ->and($resultado->e_cliente)->toBeTrue();
    });

    it('actualiza quando recebe string UUID', function (): void {
        $entidade = Entidade::factory()->create(['nome' => 'Original']);

        $dto = new ActualizarEntidadeDto(
            nome: 'Via String',
            nif: $entidade->nif,
            eCliente: false,
            eFornecedor: true,
            eEmpresaAplicacao: false,
        );

        $resultado = app(ActualizarEntidadeAction::class)->handle($entidade->id, $dto);

        expect($resultado->nome)->toBe('Via String');
    });

    it('actualizar como empresa mãe força os três flags e remove marcação anterior', function (): void {
        $anterior = Entidade::factory()->empresaAplicacao()->create();
        $entidade = Entidade::factory()->create();

        $dto = new ActualizarEntidadeDto(
            nome: $entidade->nome,
            nif: $entidade->nif,
            eCliente: false,
            eFornecedor: false,
            eEmpresaAplicacao: true,
        );

        $resultado = app(ActualizarEntidadeAction::class)->handle($entidade, $dto);

        expect($resultado->e_empresa_aplicacao)->toBeTrue()
            ->and($resultado->e_cliente)->toBeTrue()
            ->and($resultado->e_fornecedor)->toBeTrue();

        $this->assertDatabaseHas('entidades', ['id' => $anterior->id, 'e_empresa_aplicacao' => false]);
    });

    it('faz rollback quando ocorre excepção durante update', function (): void {
        $entidade = Entidade::factory()->create(['nome' => 'Original']);

        Entidade::saved(function (): void {
            throw new RuntimeException('falha simulada durante update');
        });

        $dto = new ActualizarEntidadeDto(
            nome: 'Alterado',
            nif: $entidade->nif,
            eCliente: true,
            eFornecedor: false,
            eEmpresaAplicacao: false,
        );

        expect(fn (): Entidade => app(ActualizarEntidadeAction::class)->handle($entidade, $dto))
            ->toThrow(RuntimeException::class, 'falha simulada durante update');

        $this->assertDatabaseHas('entidades', ['id' => $entidade->id, 'nome' => 'Original']);
    });
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $entidade = Entidade::factory()->create();

        $dto = new ActualizarEntidadeDto(nome: $entidade->nome, nif: $entidade->nif, eCliente: true, eFornecedor: false, eEmpresaAplicacao: false);

        expect(fn () => app(ActualizarEntidadeAction::class)->handle($entidade, $dto))
            ->toThrow(AuthorizationException::class);
    });
});
