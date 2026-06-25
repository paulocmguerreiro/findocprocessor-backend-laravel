<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\CategoriaDocumentoResource;
use App\Features\Documento\DocumentoResource;
use App\Features\Entidade\EntidadeResource;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Campos escalares', function (): void {
    it('serializa os campos com os tipos correctos', function (): void {
        $documento = Documento::factory()->make([
            'status' => EstadoDocumento::Processado,
            'id_fornecedor' => null,
            'id_cliente' => null,
            'id_categoria' => null,
            'valor' => 250.5,
            'data_documento' => '2026-01-15',
            'nome_ficheiro_original' => 'fatura.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultado = new DocumentoResource($documento)->resolve(request());

        expect($resultado['id'])->toBe($documento->id)
            ->and($resultado['status'])->toBe('PROCESSADO')
            ->and($resultado['valor'])->toBe(250.5)
            ->and($resultado['valor'])->toBeFloat()
            ->and($resultado['data_documento'])->toBe('2026-01-15')
            ->and($resultado['nome_ficheiro_original'])->toBe('fatura.pdf')
            ->and($resultado['hash_sha256'])->toBe($documento->hash_sha256)
            ->and($resultado['criado_em'])->toBeString()
            ->and($resultado['actualizado_em'])->toBeString();
    });

    it('devolve valor e data_documento a null quando nulos', function (): void {
        $documento = Documento::factory()->pendente()->make([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultado = new DocumentoResource($documento)->resolve(request());

        expect($resultado['valor'])->toBeNull()
            ->and($resultado['data_documento'])->toBeNull();
    });

    it('omite os campos internos de storage', function (): void {
        $documento = Documento::factory()->make([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultado = new DocumentoResource($documento)->resolve(request());

        expect($resultado)
            ->not->toHaveKey('disco_storage')
            ->not->toHaveKey('nome_ficheiro_storage');
    });
});

describe('Relações', function (): void {
    uses(RefreshDatabase::class);

    it('omite as relações quando não carregadas', function (): void {
        $documento = Documento::factory()->create();

        $resultado = new DocumentoResource($documento)->resolve(request());

        expect($resultado)
            ->not->toHaveKey('fornecedor')
            ->not->toHaveKey('cliente')
            ->not->toHaveKey('categoria');
    });

    it('inclui as relações quando carregadas', function (): void {
        $documento = Documento::factory()->create();
        $documento->load('fornecedor', 'cliente', 'categoria');

        $resultado = new DocumentoResource($documento)->resolve(request());

        expect($resultado)
            ->toHaveKey('fornecedor')
            ->toHaveKey('cliente')
            ->toHaveKey('categoria')
            ->and($resultado['fornecedor'])->toBeInstanceOf(EntidadeResource::class)
            ->and($resultado['cliente'])->toBeInstanceOf(EntidadeResource::class)
            ->and($resultado['categoria'])->toBeInstanceOf(CategoriaDocumentoResource::class);
    });
});
