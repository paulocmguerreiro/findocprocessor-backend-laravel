<?php

declare(strict_types=1);

use App\Features\Entidade\EntidadeResource;
use App\Models\Entidade;
use Illuminate\Http\Request;

describe('EntidadeResource', function (): void {
    it('retorna os 7 campos com os valores correctos', function (): void {
        $entidade = Entidade::factory()->clienteEFornecedor()->make([
            'nome' => 'Empresa Teste',
            'nif' => '123456789',
        ]);
        $resultado = new EntidadeResource($entidade)->toArray(new Request);

        expect($resultado)
            ->toHaveKey('id', $entidade->id)
            ->toHaveKey('nome', 'Empresa Teste')
            ->toHaveKey('nif', '123456789')
            ->toHaveKey('e_cliente', true)
            ->toHaveKey('e_fornecedor', true)
            ->toHaveKey('e_empresa_aplicacao', false)
            ->toHaveKey('deleted_at', null);
    });

    it('não inclui timestamps', function (): void {
        $entidade = Entidade::factory()->make();
        $resultado = new EntidadeResource($entidade)->toArray(new Request);

        expect($resultado)
            ->not->toHaveKey('created_at')
            ->not->toHaveKey('updated_at');
    });

    it('e_cliente, e_fornecedor e e_empresa_aplicacao são bool', function (): void {
        $entidade = Entidade::factory()->empresaAplicacao()->make();
        $resultado = new EntidadeResource($entidade)->toArray(new Request);

        expect($resultado['e_cliente'])->toBeBool()
            ->and($resultado['e_fornecedor'])->toBeBool()
            ->and($resultado['e_empresa_aplicacao'])->toBeBool();
    });

    it('deleted_at é null quando a entidade está activa', function (): void {
        $entidade = Entidade::factory()->make();
        $resultado = new EntidadeResource($entidade)->toArray(new Request);

        expect($resultado['deleted_at'])->toBeNull();
    });

    it('deleted_at é string ISO 8601 quando a entidade está inactiva', function (): void {
        $entidade = Entidade::factory()->inativa()->make();
        $resultado = new EntidadeResource($entidade)->toArray(new Request);

        expect($resultado['deleted_at'])->toBeString()->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    });
});
