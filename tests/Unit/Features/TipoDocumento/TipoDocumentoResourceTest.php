<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\CategoriaDocumentoResource;
use App\Features\TipoDocumento\TipoDocumentoResource;
use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Campos escalares', function (): void {
    it('serializa os campos com os tipos correctos', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create([
            'nome' => 'Fatura Fornecedor',
            'descricao' => 'Descrição de teste',
            'posicao_empresa_mae' => PosicaoEmpresaMae::Fornecedor,
            'espera_data_documento' => true,
            'espera_fornecedor' => false,
            'espera_cliente' => true,
            'espera_valor' => false,
        ]);

        $resultado = new TipoDocumentoResource($tipoDocumento)->resolve(request());

        expect($resultado['id'])->toBe($tipoDocumento->id)
            ->and($resultado['nome'])->toBe('Fatura Fornecedor')
            ->and($resultado['descricao'])->toBe('Descrição de teste')
            ->and($resultado['posicao_empresa_mae'])->toBe('fornecedor')
            ->and($resultado['espera_data_documento'])->toBeTrue()
            ->and($resultado['espera_fornecedor'])->toBeFalse()
            ->and($resultado['espera_cliente'])->toBeTrue()
            ->and($resultado['espera_valor'])->toBeFalse()
            ->and($resultado['criado_em'])->toBeString()
            ->and($resultado['actualizado_em'])->toBeString();
    });
});

describe('categoria e tipo_movimento', function (): void {
    it('omite categoria quando não carregada', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create();

        $resultado = new TipoDocumentoResource($tipoDocumento)->resolve(request());

        expect($resultado)->not->toHaveKey('categoria');
    });

    it('inclui categoria quando carregada', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $tipoDocumento = TipoDocumento::factory()->create(['id_categoria' => $categoria->id]);
        $tipoDocumento->load('categoria');

        $resultado = new TipoDocumentoResource($tipoDocumento)->resolve(request());

        expect($resultado)->toHaveKey('categoria')
            ->and($resultado['categoria'])->toBeInstanceOf(CategoriaDocumentoResource::class);
    });

    it('deriva tipo_movimento da categoria carregada', function (): void {
        $categoria = CategoriaDocumento::factory()->comMovimentoDebito()->create();
        $tipoDocumento = TipoDocumento::factory()->create(['id_categoria' => $categoria->id]);
        $tipoDocumento->load('categoria');

        $resultado = new TipoDocumentoResource($tipoDocumento)->resolve(request());

        expect($resultado['tipo_movimento'])->toBe('debito');
    });

    it('devolve tipo_movimento null quando a categoria não existe', function (): void {
        $tipoDocumento = TipoDocumento::factory()->make([
            'id_categoria' => '018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultado = new TipoDocumentoResource($tipoDocumento)->resolve(request());

        expect($resultado['tipo_movimento'])->toBeNull();
    });
});
