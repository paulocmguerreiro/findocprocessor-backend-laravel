<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Model', function (): void {
    it('tem uuid como chave primária', function (): void {
        $modelo = new TipoDocumento;

        expect($modelo->getKeyType())->toBe('string')
            ->and($modelo->getIncrementing())->toBeFalse();
    });

    it('tem fillable correcto', function (): void {
        $modelo = new TipoDocumento;

        expect($modelo->getFillable())->toBe([
            'nome', 'descricao', 'id_categoria', 'posicao_empresa_mae',
            'espera_data_documento', 'espera_fornecedor', 'espera_cliente', 'espera_valor',
        ]);
    });

    it('tem timestamps', function (): void {
        expect((new TipoDocumento)->usesTimestamps())->toBeTrue();
    });

    it('usa a tabela tipos_documento', function (): void {
        expect((new TipoDocumento)->getTable())->toBe('tipos_documento');
    });
});

describe('Casts', function (): void {
    it('cast posicao_empresa_mae para PosicaoEmpresaMae enum', function (): void {
        $tipoDocumento = TipoDocumento::factory()->make(['posicao_empresa_mae' => 'fornecedor']);

        expect($tipoDocumento->posicao_empresa_mae)->toBeInstanceOf(PosicaoEmpresaMae::class)
            ->and($tipoDocumento->posicao_empresa_mae)->toBe(PosicaoEmpresaMae::Fornecedor);
    });

    it('cast dos 4 campos espera_* para boolean', function (): void {
        $tipoDocumento = TipoDocumento::factory()->make([
            'espera_data_documento' => 1,
            'espera_fornecedor' => 0,
            'espera_cliente' => 1,
            'espera_valor' => 0,
        ]);

        expect($tipoDocumento->espera_data_documento)->toBeTrue()
            ->and($tipoDocumento->espera_fornecedor)->toBeFalse()
            ->and($tipoDocumento->espera_cliente)->toBeTrue()
            ->and($tipoDocumento->espera_valor)->toBeFalse();
    });
});

describe('Relações', function (): void {
    uses(RefreshDatabase::class);

    it('belongsTo categoria (CategoriaDocumento)', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $tipoDocumento = TipoDocumento::factory()->create(['id_categoria' => $categoria->id]);

        expect($tipoDocumento->categoria)->toBeInstanceOf(CategoriaDocumento::class)
            ->and($tipoDocumento->categoria->id)->toBe($categoria->id);
    });

    it('categoria() carrega categoria inactiva (withTrashed)', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $tipoDocumento = TipoDocumento::factory()->create(['id_categoria' => $categoria->id]);

        $categoria->delete();

        expect($tipoDocumento->fresh()->categoria)->toBeInstanceOf(CategoriaDocumento::class)
            ->and($tipoDocumento->fresh()->categoria->id)->toBe($categoria->id);
    });
});

describe('Constraints BD', function (): void {
    uses(RefreshDatabase::class);

    it('não permite eliminar uma categoria referenciada por um TipoDocumento (restrictOnDelete)', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        TipoDocumento::factory()->create(['id_categoria' => $categoria->id]);

        expect(fn () => $categoria->forceDelete())->toThrow(QueryException::class);
    });

    it('não permite nome duplicado', function (): void {
        TipoDocumento::factory()->create(['nome' => 'Fatura Mensal']);

        expect(fn () => TipoDocumento::factory()->create(['nome' => 'Fatura Mensal']))
            ->toThrow(QueryException::class);
    });
});

describe('Factory — base', function (): void {
    it('cria instância válida', function (): void {
        $tipoDocumento = TipoDocumento::factory()->make();

        expect($tipoDocumento->nome)->toBeString()->not->toBeEmpty()
            ->and($tipoDocumento->descricao)->toBeString()->not->toBeEmpty()
            ->and($tipoDocumento->posicao_empresa_mae)->toBeInstanceOf(PosicaoEmpresaMae::class)
            ->and($tipoDocumento->espera_data_documento)->toBeTrue()
            ->and($tipoDocumento->espera_fornecedor)->toBeTrue()
            ->and($tipoDocumento->espera_cliente)->toBeTrue()
            ->and($tipoDocumento->espera_valor)->toBeTrue();
    });

    it('persiste com id_categoria válido', function (): void {
        $tipoDocumento = TipoDocumento::factory()->create();

        expect($tipoDocumento->id_categoria)->not->toBeNull()
            ->and(CategoriaDocumento::query()->whereKey($tipoDocumento->id_categoria)->exists())->toBeTrue();
    });
});
