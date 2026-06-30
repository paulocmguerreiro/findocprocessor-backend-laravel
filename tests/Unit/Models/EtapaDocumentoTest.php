<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Models\EtapaDocumento;
use App\Models\User;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

describe('Model', function (): void {
    it('tem uuid como chave primária', function (): void {
        $modelo = new EtapaDocumento;

        expect($modelo->getKeyType())->toBe('string')
            ->and($modelo->getIncrementing())->toBeFalse();
    });

    it('tem fillable correcto', function (): void {
        expect((new EtapaDocumento)->getFillable())->toBe([
            'id_documento', 'estado', 'motivo', 'id_utilizador',
        ]);
    });

    it('usa a tabela etapas_documento', function (): void {
        expect((new EtapaDocumento)->getTable())->toBe('etapas_documento');
    });
});

describe('Append-only', function (): void {
    it('não tem coluna updated_at', function (): void {
        expect(Schema::hasColumn('etapas_documento', 'updated_at'))->toBeFalse()
            ->and(Schema::hasColumn('etapas_documento', 'created_at'))->toBeTrue();
    })->uses(RefreshDatabase::class);

    it('define UPDATED_AT a null', function (): void {
        expect(EtapaDocumento::UPDATED_AT)->toBeNull();
    });

    it('preenche apenas created_at ao criar', function (): void {
        $etapa = EtapaDocumento::factory()->create();

        expect($etapa->created_at)->toBeInstanceOf(Carbon::class)
            ->and($etapa->getAttributes())->not->toHaveKey('updated_at');
    })->uses(RefreshDatabase::class);
});

describe('Casts', function (): void {
    it('cast estado para EstadoDocumento enum', function (): void {
        $etapa = EtapaDocumento::factory()->make(['estado' => EstadoDocumento::Erro]);

        expect($etapa->estado)->toBeInstanceOf(EstadoDocumento::class)
            ->and($etapa->estado)->toBe(EstadoDocumento::Erro);
    });
});

describe('Relações', function (): void {
    uses(RefreshDatabase::class);

    it('belongsTo documento', function (): void {
        $documento = Documento::factory()->create();
        $etapa = EtapaDocumento::factory()->create(['id_documento' => $documento->id]);

        expect($etapa->documento)->toBeInstanceOf(Documento::class)
            ->and($etapa->documento->id)->toBe($documento->id);
    });

    it('belongsTo utilizador', function (): void {
        $utilizador = User::factory()->create();
        $etapa = EtapaDocumento::factory()->create(['id_utilizador' => $utilizador->id]);

        expect($etapa->utilizador)->toBeInstanceOf(User::class)
            ->and($etapa->utilizador->id)->toBe($utilizador->id);
    });

    it('utilizador é null num passo automático', function (): void {
        $etapa = EtapaDocumento::factory()->create(['id_utilizador' => null]);

        expect($etapa->utilizador)->toBeNull();
    });

    it('elimina as etapas em cascata quando o documento é eliminado (cascadeOnDelete)', function (): void {
        $documento = Documento::factory()->create();
        $etapa = EtapaDocumento::factory()->create(['id_documento' => $documento->id]);

        $documento->delete();

        expect(EtapaDocumento::find($etapa->id))->toBeNull();
    });

    it('preserva id_utilizador quando o utilizador é soft-deleted (restrictOnDelete)', function (): void {
        $utilizador = User::factory()->create();
        $etapa = EtapaDocumento::factory()->create(['id_utilizador' => $utilizador->id]);

        $utilizador->delete(); // soft delete — a autoria da etapa é preservada

        expect($etapa->fresh()->id_utilizador)->toBe($utilizador->id);
    });
});

describe('Factory — states', function (): void {
    uses(RefreshDatabase::class);

    it('cada state define o estado esperado', function (string $state, EstadoDocumento $estado): void {
        $etapa = EtapaDocumento::factory()->{$state}()->create();

        expect($etapa->estado)->toBe($estado);
    })->with([
        'processado' => ['processado', EstadoDocumento::Processado],
        'erro' => ['erro', EstadoDocumento::Erro],
        'perigoso' => ['perigoso', EstadoDocumento::Perigoso],
        'manual' => ['manual', EstadoDocumento::Processado],
    ]);

    it('base é Pendente sem motivo nem utilizador', function (): void {
        $etapa = EtapaDocumento::factory()->create();

        expect($etapa->estado)->toBe(EstadoDocumento::Pendente)
            ->and($etapa->motivo)->toBeNull()
            ->and($etapa->id_utilizador)->toBeNull();
    });

    it('erro e perigoso têm motivo preenchido', function (string $state): void {
        $etapa = EtapaDocumento::factory()->{$state}()->create();

        expect($etapa->motivo)->not->toBeNull();
    })->with(['erro', 'perigoso']);

    it('manual tem id_utilizador preenchido', function (): void {
        $etapa = EtapaDocumento::factory()->manual()->create();

        expect($etapa->id_utilizador)->not->toBeNull();
    });
});
