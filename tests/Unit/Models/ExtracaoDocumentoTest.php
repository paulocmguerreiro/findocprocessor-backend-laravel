<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('Model', function (): void {
    it('tem uuid como chave primária', function (): void {
        $modelo = new ExtracaoDocumento;

        expect($modelo->getKeyType())->toBe('string')
            ->and($modelo->getIncrementing())->toBeFalse();
    });

    it('tem fillable correcto', function (): void {
        expect((new ExtracaoDocumento)->getFillable())->toBe([
            'id_documento', 'extracao_reclamada_em',
            'extracao_tentativas', 'texto_extraido', 'dados_json',
        ]);
    });

    it('usa a tabela extracoes_documento', function (): void {
        expect((new ExtracaoDocumento)->getTable())->toBe('extracoes_documento');
    });
});

describe('Casts', function (): void {
    it('cast extracao_reclamada_em para Carbon', function (): void {
        $extracao = ExtracaoDocumento::factory()->reclamada()->create();

        expect($extracao->extracao_reclamada_em)->toBeInstanceOf(Carbon::class);
    });

    it('cast extracao_tentativas para int', function (): void {
        $extracao = ExtracaoDocumento::factory()->comTentativas(3)->create();

        expect($extracao->extracao_tentativas)->toBeInt()->toBe(3);
    });

    it('cast dados_json para array', function (): void {
        $extracao = ExtracaoDocumento::factory()->comDadosExtraidos()->create();

        expect($extracao->dados_json)->toBeArray();
    });
});

describe('Relações', function (): void {
    it('belongsTo documento', function (): void {
        $documento = Documento::factory()->create();
        $extracao = ExtracaoDocumento::factory()->create(['id_documento' => $documento->id]);

        expect($extracao->documento)->toBeInstanceOf(Documento::class)
            ->and($extracao->documento->id)->toBe($documento->id);
    });

    it('não permite duas linhas para o mesmo documento (id_documento único)', function (): void {
        $documento = Documento::factory()->create();
        ExtracaoDocumento::factory()->create(['id_documento' => $documento->id]);

        expect(fn () => ExtracaoDocumento::factory()->create(['id_documento' => $documento->id]))
            ->toThrow(QueryException::class);
    });

    it('elimina a extracao em cascata quando o documento é eliminado (cascadeOnDelete)', function (): void {
        $documento = Documento::factory()->create();
        $extracao = ExtracaoDocumento::factory()->create(['id_documento' => $documento->id]);

        $documento->delete();

        expect(ExtracaoDocumento::find($extracao->id))->toBeNull();
    });
});

describe('Factory — states', function (): void {
    it('base é scratch space vazio: sem tentativas, sem lease, sem dados', function (): void {
        $extracao = ExtracaoDocumento::factory()->create();

        expect($extracao->extracao_tentativas)->toBe(0)
            ->and($extracao->extracao_reclamada_em)->toBeNull()
            ->and($extracao->texto_extraido)->toBeNull()
            ->and($extracao->dados_json)->toBeNull();
    });

    it('reclamada preenche extracao_reclamada_em', function (): void {
        $extracao = ExtracaoDocumento::factory()->reclamada()->create();

        expect($extracao->extracao_reclamada_em)->not->toBeNull();
    });

    it('comDadosExtraidos preenche o payload PII (texto_extraido/dados_json)', function (): void {
        $extracao = ExtracaoDocumento::factory()->comDadosExtraidos()->create();

        expect($extracao->texto_extraido)->not->toBeNull()
            ->and($extracao->dados_json)->toBeArray();
    });

    it('comTentativas define o contador', function (): void {
        $extracao = ExtracaoDocumento::factory()->comTentativas(2)->create();

        expect($extracao->extracao_tentativas)->toBe(2);
    });
});
