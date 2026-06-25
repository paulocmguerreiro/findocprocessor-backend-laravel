<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

describe('Model', function (): void {
    it('tem uuid como chave primária', function (): void {
        $modelo = new Documento;

        expect($modelo->getKeyType())->toBe('string')
            ->and($modelo->getIncrementing())->toBeFalse();
    });

    it('tem fillable correcto', function (): void {
        $modelo = new Documento;

        expect($modelo->getFillable())->toBe([
            'status', 'id_fornecedor', 'id_cliente', 'id_categoria', 'valor',
            'data_documento', 'nome_ficheiro_original', 'disco_storage',
            'nome_ficheiro_storage', 'hash_sha256',
        ]);
    });

    it('tem timestamps', function (): void {
        expect((new Documento)->usesTimestamps())->toBeTrue();
    });

    it('usa a tabela documentos', function (): void {
        expect((new Documento)->getTable())->toBe('documentos');
    });
});

describe('Casts', function (): void {
    it('cast status para EstadoDocumento enum', function (): void {
        $documento = Documento::factory()->make(['status' => EstadoDocumento::Enviado]);

        expect($documento->status)->toBeInstanceOf(EstadoDocumento::class)
            ->and($documento->status)->toBe(EstadoDocumento::Enviado);
    });

    it('cast valor para string decimal com 2 casas', function (): void {
        $documento = Documento::factory()->make(['valor' => 12.5]);

        expect($documento->valor)->toBeString()->toBe('12.50');
    });

    it('cast data_documento para Carbon', function (): void {
        $documento = Documento::factory()->make(['data_documento' => '2026-01-15']);

        expect($documento->data_documento)->toBeInstanceOf(Carbon::class);
    });
});

describe('Relações', function (): void {
    uses(RefreshDatabase::class);

    it('belongsTo fornecedor (Entidade)', function (): void {
        $fornecedor = Entidade::factory()->fornecedor()->create();
        $documento = Documento::factory()->create(['id_fornecedor' => $fornecedor->id]);

        expect($documento->fornecedor)->toBeInstanceOf(Entidade::class)
            ->and($documento->fornecedor->id)->toBe($fornecedor->id);
    });

    it('belongsTo cliente (Entidade)', function (): void {
        $cliente = Entidade::factory()->cliente()->create();
        $documento = Documento::factory()->create(['id_cliente' => $cliente->id]);

        expect($documento->cliente)->toBeInstanceOf(Entidade::class)
            ->and($documento->cliente->id)->toBe($cliente->id);
    });

    it('belongsTo categoria (CategoriaDocumento)', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $documento = Documento::factory()->create(['id_categoria' => $categoria->id]);

        expect($documento->categoria)->toBeInstanceOf(CategoriaDocumento::class)
            ->and($documento->categoria->id)->toBe($categoria->id);
    });

    it('coloca id_fornecedor a null quando a entidade é eliminada (nullOnDelete)', function (): void {
        $fornecedor = Entidade::factory()->fornecedor()->create();
        $documento = Documento::factory()->create(['id_fornecedor' => $fornecedor->id]);

        $fornecedor->delete();

        expect($documento->fresh()->id_fornecedor)->toBeNull();
    });

    it('coloca id_categoria a null quando a categoria é eliminada (nullOnDelete)', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $documento = Documento::factory()->create(['id_categoria' => $categoria->id]);

        $categoria->delete();

        expect($documento->fresh()->id_categoria)->toBeNull();
    });
});

describe('Scopes', function (): void {
    uses(RefreshDatabase::class);

    it('whereEstado filtra pelo estado passado', function (): void {
        Documento::factory()->enviado()->create();
        Documento::factory()->pendente()->create();

        expect(Documento::whereEstado(EstadoDocumento::Enviado)->count())->toBe(1);
    });

    it('whereProcessado retorna só processados', function (): void {
        Documento::factory()->processado()->create();
        Documento::factory()->pendente()->create();

        expect(Documento::whereProcessado()->count())->toBe(1);
    });

    it('wherePendente retorna só pendentes', function (): void {
        Documento::factory()->pendente()->create();
        Documento::factory()->processado()->create();

        expect(Documento::wherePendente()->count())->toBe(1);
    });

    it('wherePerigoso retorna só perigosos', function (): void {
        Documento::factory()->perigoso()->create();
        Documento::factory()->processado()->create();

        expect(Documento::wherePerigoso()->count())->toBe(1);
    });

    it('whereErro retorna só com erro', function (): void {
        Documento::factory()->erro()->create();
        Documento::factory()->processado()->create();

        expect(Documento::whereErro()->count())->toBe(1);
    });
});

describe('Factory — states', function (): void {
    it('cada state define o disco_storage e o status correctos', function (
        string $state,
        EstadoDocumento $estado,
        string $disco,
    ): void {
        $documento = Documento::factory()->{$state}()->make();

        expect($documento->status)->toBe($estado)
            ->and($documento->disco_storage)->toBe($disco);
    })->with([
        'pendente' => ['pendente', EstadoDocumento::Pendente, 'entrada'],
        'aguardaEnvio' => ['aguardaEnvio', EstadoDocumento::AguardaEnvio, 'entrada'],
        'enviado' => ['enviado', EstadoDocumento::Enviado, 'enviado'],
        'aguardaResposta' => ['aguardaResposta', EstadoDocumento::AguardaResposta, 'enviado'],
        'processado' => ['processado', EstadoDocumento::Processado, 'processado'],
        'erro' => ['erro', EstadoDocumento::Erro, 'erro'],
        'perigoso' => ['perigoso', EstadoDocumento::Perigoso, 'perigoso'],
    ]);

    it('states parciais não têm dados de domínio', function (): void {
        $documento = Documento::factory()->pendente()->make();

        expect($documento->id_fornecedor)->toBeNull()
            ->and($documento->id_cliente)->toBeNull()
            ->and($documento->id_categoria)->toBeNull()
            ->and($documento->valor)->toBeNull()
            ->and($documento->data_documento)->toBeNull();
    });
});
