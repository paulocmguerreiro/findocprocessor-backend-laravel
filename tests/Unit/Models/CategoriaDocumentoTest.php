<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Model', function (): void {
    it('tem uuid como chave primária', function (): void {
        $modelo = new CategoriaDocumento;

        expect($modelo->getKeyType())->toBe('string')
            ->and($modelo->getIncrementing())->toBeFalse();
    });

    it('tem fillable correcto', function (): void {
        $modelo = new CategoriaDocumento;

        expect($modelo->getFillable())->toContain('nome', 'slug', 'tipo_movimento');
    });

    it('cast tipo_movimento para TipoMovimento enum', function (): void {
        $modelo = CategoriaDocumento::factory()->make(['tipo_movimento' => 'debito']);

        expect($modelo->tipo_movimento)->toBeInstanceOf(TipoMovimento::class);
    });

    it('tem timestamps', function (): void {
        $modelo = new CategoriaDocumento;

        expect($modelo->usesTimestamps())->toBeTrue();
    });
});

describe('Factory — base', function (): void {
    it('cria instância válida', function (): void {
        $categoria = CategoriaDocumento::factory()->make();

        expect($categoria->nome)->toBeString()->not->toBeEmpty()
            ->and($categoria->slug)->toBeString()->not->toBeEmpty()
            ->and($categoria->tipo_movimento)->toBeInstanceOf(TipoMovimento::class);
    });
});

describe('Constraints BD', function (): void {
    uses(RefreshDatabase::class);

    it('não permite slug duplicado', function (): void {
        CategoriaDocumento::factory()->create(['slug' => 'fatura-mensal']);

        expect(fn () => CategoriaDocumento::factory()->create(['slug' => 'fatura-mensal']))
            ->toThrow(QueryException::class);
    });

    it('não permite uuid duplicado', function (): void {
        $id = '018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b';
        CategoriaDocumento::factory()->create(['id' => $id]);

        expect(fn () => CategoriaDocumento::factory()->create(['id' => $id]))
            ->toThrow(QueryException::class);
    });

    it('não permite nome nulo', function (): void {
        expect(fn () => CategoriaDocumento::factory()->create(['nome' => null]))
            ->toThrow(QueryException::class);
    });

    it('não permite slug nulo', function (): void {
        expect(fn () => CategoriaDocumento::factory()->create(['slug' => null]))
            ->toThrow(QueryException::class);
    });
});

describe('SoftDeletes', function (): void {
    uses(RefreshDatabase::class);

    it('soft-deleta (deleted_at preenchido, registo permanece na BD)', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        $categoria->delete();

        $this->assertSoftDeleted('categorias_documento', ['id' => $categoria->id]);
    });

    it('exclui categorias inactivas por defeito das queries', function (): void {
        CategoriaDocumento::factory()->inativa()->create();
        CategoriaDocumento::factory()->create();

        expect(CategoriaDocumento::count())->toBe(1);
    });

    it('state inativa define deleted_at', function (): void {
        $categoria = CategoriaDocumento::factory()->inativa()->make();

        expect($categoria->deleted_at)->not->toBeNull();
    });
});

describe('Factory — states', function (): void {
    it('state comMovimentoDebito define tipo_movimento como Debito', function (): void {
        $categoria = CategoriaDocumento::factory()->comMovimentoDebito()->make();

        expect($categoria->tipo_movimento)->toBe(TipoMovimento::Debito);
    });

    it('state comMovimentoCredito define tipo_movimento como Credito', function (): void {
        $categoria = CategoriaDocumento::factory()->comMovimentoCredito()->make();

        expect($categoria->tipo_movimento)->toBe(TipoMovimento::Credito);
    });

    it('state comMovimentoNeutro define tipo_movimento como Neutro', function (): void {
        $categoria = CategoriaDocumento::factory()->comMovimentoNeutro()->make();

        expect($categoria->tipo_movimento)->toBe(TipoMovimento::Neutro);
    });

    it('state inativa define deleted_at', function (): void {
        $categoria = CategoriaDocumento::factory()->inativa()->make();

        expect($categoria->deleted_at)->not->toBeNull();
    });
});
