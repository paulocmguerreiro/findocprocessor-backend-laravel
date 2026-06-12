<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\CategoriaDocumentoResource;
use App\Models\CategoriaDocumento;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Http\Request;

describe('CategoriaDocumentoResource', function (): void {
    it('retorna os 4 campos com os valores correctos', function (): void {
        $categoria = CategoriaDocumento::factory()->comMovimentoDebito()->make([
            'nome' => 'Fatura de Fornecedor',
            'slug' => 'fatura-de-fornecedor',
        ]);
        $resultado = (new CategoriaDocumentoResource($categoria))->toArray(new Request);

        expect($resultado)
            ->toHaveKey('id', $categoria->id)
            ->toHaveKey('nome', $categoria->nome)
            ->toHaveKey('slug', $categoria->slug)
            ->toHaveKey('tipo_movimento', TipoMovimento::Debito->value);
    });

    it('não inclui timestamps', function (): void {
        $categoria = CategoriaDocumento::factory()->make();
        $resultado = (new CategoriaDocumentoResource($categoria))->toArray(new Request);

        expect($resultado)
            ->not->toHaveKey('created_at')
            ->not->toHaveKey('updated_at');
    });

    it('tipo_movimento é o valor string do enum', function (): void {
        $categoria = CategoriaDocumento::factory()->comMovimentoDebito()->make();
        $resultado = (new CategoriaDocumentoResource($categoria))->toArray(new Request);

        expect($resultado['tipo_movimento'])
            ->toBeString()
            ->toBe(TipoMovimento::Debito->value);
    });
});