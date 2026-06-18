<?php

declare(strict_types=1);

use App\Models\Entidade;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Model', function (): void {
    it('tem uuid como chave primária', function (): void {
        $modelo = new Entidade;

        expect($modelo->getKeyType())->toBe('string')
            ->and($modelo->getIncrementing())->toBeFalse();
    });

    it('tem fillable correcto', function (): void {
        $modelo = new Entidade;

        expect($modelo->getFillable())->toBe(['nome', 'nif', 'e_cliente', 'e_fornecedor', 'e_empresa_aplicacao']);
    });

    it('tem timestamps', function (): void {
        $modelo = new Entidade;

        expect($modelo->usesTimestamps())->toBeTrue();
    });
});

describe('Casts', function (): void {
    it('cast boolean nas três flags', function (): void {
        $entidade = Entidade::factory()->make([
            'e_cliente' => true,
            'e_fornecedor' => false,
            'e_empresa_aplicacao' => true,
        ]);

        expect($entidade->e_cliente)->toBeBool()
            ->and($entidade->e_fornecedor)->toBeBool()
            ->and($entidade->e_empresa_aplicacao)->toBeBool();
    });
});

describe('Scopes', function (): void {
    uses(RefreshDatabase::class);

    it('whereCliente retorna só clientes', function (): void {
        Entidade::factory()->cliente()->create();
        Entidade::factory()->fornecedor()->create();

        expect(Entidade::whereCliente()->count())->toBe(1);
    });

    it('whereFornecedor retorna só fornecedores', function (): void {
        Entidade::factory()->fornecedor()->create();
        Entidade::factory()->cliente()->create();

        expect(Entidade::whereFornecedor()->count())->toBe(1);
    });

    it('whereEmpresaAplicacao retorna só a empresa mãe', function (): void {
        Entidade::factory()->empresaAplicacao()->create();
        Entidade::factory()->cliente()->create();

        expect(Entidade::whereEmpresaAplicacao()->count())->toBe(1);
    });

    it('whereCliente exclui não-clientes', function (): void {
        $fornecedor = Entidade::factory()->fornecedor()->create();

        $resultado = Entidade::whereCliente()->get();

        expect($resultado->contains($fornecedor))->toBeFalse();
    });
});

describe('Factory — states', function (): void {
    it('state cliente define e_cliente=true', function (): void {
        $entidade = Entidade::factory()->cliente()->make();

        expect($entidade->e_cliente)->toBeTrue()
            ->and($entidade->e_fornecedor)->toBeFalse()
            ->and($entidade->e_empresa_aplicacao)->toBeFalse();
    });

    it('state fornecedor define e_fornecedor=true', function (): void {
        $entidade = Entidade::factory()->fornecedor()->make();

        expect($entidade->e_fornecedor)->toBeTrue()
            ->and($entidade->e_cliente)->toBeFalse()
            ->and($entidade->e_empresa_aplicacao)->toBeFalse();
    });

    it('state clienteEFornecedor define ambas as flags', function (): void {
        $entidade = Entidade::factory()->clienteEFornecedor()->make();

        expect($entidade->e_cliente)->toBeTrue()
            ->and($entidade->e_fornecedor)->toBeTrue()
            ->and($entidade->e_empresa_aplicacao)->toBeFalse();
    });

    it('state empresaAplicacao define as três flags', function (): void {
        $entidade = Entidade::factory()->empresaAplicacao()->make();

        expect($entidade->e_empresa_aplicacao)->toBeTrue()
            ->and($entidade->e_cliente)->toBeTrue()
            ->and($entidade->e_fornecedor)->toBeTrue();
    });
});
