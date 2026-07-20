<?php

declare(strict_types=1);

use App\Features\Documento\Atribuicao\ReivindicarDocumentoEmEtapaAction;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reclamarEmEtapa(EstadoDocumento $estado = EstadoDocumento::AnaliseTexto): ?Documento
{
    return app(ReivindicarDocumentoEmEtapaAction::class)->handle($estado);
}

function leaseDe(Documento $documento): ?ExtracaoDocumento
{
    return ExtracaoDocumento::query()->where('id_documento', $documento->id)->first();
}

it('reclama um documento sem linha de extracção e cria o lease', function (): void {
    $documento = Documento::factory()->analiseTexto()->create();

    $reclamado = reclamarEmEtapa();

    expect($reclamado)->not->toBeNull()
        ->and($reclamado?->id)->toBe($documento->id);

    $lease = leaseDe($documento);
    expect($lease)->not->toBeNull()
        ->and($lease?->extracao_reclamada_em)->not->toBeNull();
});

it('reclama um documento cujo lease está nulo', function (): void {
    $documento = Documento::factory()->analiseTexto()->create();
    ExtracaoDocumento::factory()->for($documento, 'documento')->create(['extracao_reclamada_em' => null]);

    $reclamado = reclamarEmEtapa();

    expect($reclamado?->id)->toBe($documento->id)
        ->and(leaseDe($documento)?->extracao_reclamada_em)->not->toBeNull();
});

it('reclama um documento cujo lease expirou (recupera-o) e renova o lease', function (): void {
    $documento = Documento::factory()->analiseTexto()->create();
    $expirado = now()->subSeconds(config()->integer('extracao.ttl_lease') + 60);
    ExtracaoDocumento::factory()->for($documento, 'documento')->create(['extracao_reclamada_em' => $expirado]);

    $reclamado = reclamarEmEtapa();

    expect($reclamado?->id)->toBe($documento->id)
        ->and(leaseDe($documento)?->extracao_reclamada_em?->greaterThan($expirado))->toBeTrue();
});

it('não reclama um documento com lease recente (ainda dentro do TTL)', function (): void {
    $documento = Documento::factory()->analiseTexto()->create();
    ExtracaoDocumento::factory()->for($documento, 'documento')->create(['extracao_reclamada_em' => now()->subSeconds(5)]);

    expect(reclamarEmEtapa())->toBeNull();
});

it('devolve null quando não há candidato no estado pedido', function (): void {
    Documento::factory()->analiseOcr()->create();

    expect(reclamarEmEtapa(EstadoDocumento::AnaliseTexto))->toBeNull();
});

it('ignora documentos noutros estados — incl. Processado (documento manual, CA-09)', function (): void {
    Documento::factory()->processado()->create();
    Documento::factory()->analiseCloud()->create();

    expect(reclamarEmEtapa(EstadoDocumento::AnaliseTexto))->toBeNull();
});

it('reclama o documento mais antigo primeiro (FIFO)', function (): void {
    $antigo = Documento::factory()->analiseTexto()->create(['created_at' => now()->subMinutes(10)]);
    Documento::factory()->analiseTexto()->create(['created_at' => now()]);

    expect(reclamarEmEtapa()?->id)->toBe($antigo->id);
});
