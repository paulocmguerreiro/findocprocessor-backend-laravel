<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
use App\Models\EtapaDocumento;
use App\Models\ExtracaoDocumento;
use App\Models\User;
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
            'estado', 'id_responsavel', 'id_fornecedor', 'id_cliente', 'id_categoria', 'valor',
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
    it('cast estado para EstadoDocumento enum', function (): void {
        $documento = Documento::factory()->make(['estado' => EstadoDocumento::Enviado]);

        expect($documento->estado)->toBeInstanceOf(EstadoDocumento::class)
            ->and($documento->estado)->toBe(EstadoDocumento::Enviado);
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

    it('belongsTo responsavel (User)', function (): void {
        $responsavel = User::factory()->create();
        $documento = Documento::factory()->create(['id_responsavel' => $responsavel->id]);

        expect($documento->responsavel)->toBeInstanceOf(User::class)
            ->and($documento->responsavel->id)->toBe($responsavel->id);
    });

    it('preserva id_responsavel quando o utilizador é soft-deleted (restrictOnDelete)', function (): void {
        $responsavel = User::factory()->create();
        $documento = Documento::factory()->create(['id_responsavel' => $responsavel->id]);

        $responsavel->delete(); // soft delete — a autoria é preservada

        expect($documento->fresh()->id_responsavel)->toBe($responsavel->id);
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

    it('fornecedor() carrega entidade inactiva (withTrashed)', function (): void {
        $fornecedor = Entidade::factory()->fornecedor()->create();
        $documento = Documento::factory()->create(['id_fornecedor' => $fornecedor->id]);

        $fornecedor->delete();

        expect($documento->fresh()->fornecedor)->toBeInstanceOf(Entidade::class)
            ->and($documento->fresh()->fornecedor->id)->toBe($fornecedor->id);
    });

    it('cliente() carrega entidade inactiva (withTrashed)', function (): void {
        $cliente = Entidade::factory()->cliente()->create();
        $documento = Documento::factory()->create(['id_cliente' => $cliente->id]);

        $cliente->delete();

        expect($documento->fresh()->cliente)->toBeInstanceOf(Entidade::class)
            ->and($documento->fresh()->cliente->id)->toBe($cliente->id);
    });

    it('categoria() carrega categoria inactiva (withTrashed)', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $documento = Documento::factory()->create(['id_categoria' => $categoria->id]);

        $categoria->delete();

        expect($documento->fresh()->categoria)->toBeInstanceOf(CategoriaDocumento::class)
            ->and($documento->fresh()->categoria->id)->toBe($categoria->id);
    });

    it('extracao é null quando o documento nunca entrou no pipeline de extracao', function (): void {
        $documento = Documento::factory()->create();

        expect($documento->extracao)->toBeNull();
    });

    it('hasOne extracao devolve a linha associada', function (): void {
        $documento = Documento::factory()->create();
        $extracao = ExtracaoDocumento::factory()->for($documento, 'documento')->create();

        expect($documento->extracao)->toBeInstanceOf(ExtracaoDocumento::class)
            ->and($documento->extracao->id)->toBe($extracao->id);
    });
});

describe('historico', function (): void {
    uses(RefreshDatabase::class);

    it('hasMany etapas ordenadas por created_at ascendente', function (): void {
        $documento = Documento::factory()->create();

        $maisRecente = EtapaDocumento::factory()->create([
            'id_documento' => $documento->id,
            'created_at' => Carbon::parse('2026-06-03 10:00:00'),
        ]);
        $maisAntiga = EtapaDocumento::factory()->create([
            'id_documento' => $documento->id,
            'created_at' => Carbon::parse('2026-06-01 10:00:00'),
        ]);
        $intermedia = EtapaDocumento::factory()->create([
            'id_documento' => $documento->id,
            'created_at' => Carbon::parse('2026-06-02 10:00:00'),
        ]);

        $historico = $documento->historico;

        expect($historico)->toHaveCount(3)
            ->and($historico->first())->toBeInstanceOf(EtapaDocumento::class)
            ->and($historico->pluck('id')->all())->toBe([
                $maisAntiga->id, $intermedia->id, $maisRecente->id,
            ]);
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
    it('cada state define o disco_storage e o estado correctos', function (
        string $state,
        EstadoDocumento $estado,
        string $disco,
    ): void {
        $documento = Documento::factory()->{$state}()->make();

        expect($documento->estado)->toBe($estado)
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
