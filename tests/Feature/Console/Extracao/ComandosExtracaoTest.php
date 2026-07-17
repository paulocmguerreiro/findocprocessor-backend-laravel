<?php

declare(strict_types=1);

use App\Infrastructure\Malware\ContratoAnalisadorMalware;
use App\Infrastructure\Malware\ResultadoAnaliseMalware;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
    Storage::fake('enviado');
    Storage::fake('processado');
    Storage::fake('perigoso');
    Storage::fake('erro');
});

it('extracao:run-scan tria em lote os documentos Pendente', function (): void {
    $documentos = Documento::factory()->pendente()->count(2)->create();
    $documentos->each(fn (Documento $doc) => Storage::disk('entrada')->put($doc->nome_ficheiro_storage, 'conteudo'));
    app()->instance(ContratoAnalisadorMalware::class, Mockery::mock(ContratoAnalisadorMalware::class, function ($mock): void {
        $mock->shouldReceive('analisar')->andReturn(ResultadoAnaliseMalware::limpo());
    }));

    $this->artisan('extracao:run-scan')->assertSuccessful();

    expect(Documento::query()->whereEstado(EstadoDocumento::AnaliseTexto)->count())->toBe(2);
});

it('extracao:run-parser processa em lote (imagens saltam para AnaliseOcr)', function (): void {
    $documentos = Documento::factory()->analiseTexto()->count(2)->sequence(
        ['nome_ficheiro_storage' => 'a.jpg'],
        ['nome_ficheiro_storage' => 'b.png'],
    )->create();
    $documentos->each(fn (Documento $doc) => Storage::disk('entrada')->put($doc->nome_ficheiro_storage, 'imagem'));

    $this->artisan('extracao:run-parser')->assertSuccessful();

    expect(Documento::query()->whereEstado(EstadoDocumento::AnaliseOcr)->count())->toBe(2);
});

it('extracao:run-tesseract processa apenas 1 documento por ciclo', function (): void {
    $documentos = Documento::factory()->analiseOcr()->count(2)->create();
    $documentos->each(function (Documento $doc): void {
        Storage::disk('entrada')->put($doc->nome_ficheiro_storage, (string) file_get_contents(base_path('tests/Fixtures/Extracao/pdf-corrompido.pdf')));
    });

    $this->artisan('extracao:run-tesseract')->assertSuccessful();

    // Só 1 documento foi reclamado (só 1 linha de extracção criada) — limite 1/ciclo.
    expect(ExtracaoDocumento::query()->count())->toBe(1);
});

it('extracao:run-ia-local processa apenas 1 documento por ciclo', function (): void {
    config(['extracao.local.activa' => false]); // guarda salta p/ AnaliseCloud sem invocar IA
    Documento::factory()->analiseIaLocal()->count(2)->create()
        ->each(fn (Documento $doc) => Storage::disk('enviado')->put($doc->nome_ficheiro_storage, 'conteudo'));

    $this->artisan('extracao:run-ia-local')->assertSuccessful();

    expect(Documento::query()->whereEstado(EstadoDocumento::AnaliseCloud)->count())->toBe(1)
        ->and(Documento::query()->whereEstado(EstadoDocumento::AnaliseIaLocal)->count())->toBe(1);
});

it('extracao:run-ia-cloud processa em lote (cloud inactiva → Erro)', function (): void {
    config(['extracao.cloud.activa' => false]);
    Documento::factory()->analiseCloud()->count(2)->create()
        ->each(fn (Documento $doc) => Storage::disk('enviado')->put($doc->nome_ficheiro_storage, 'conteudo'));

    $this->artisan('extracao:run-ia-cloud')->assertSuccessful();

    expect(Documento::query()->whereEstado(EstadoDocumento::Erro)->count())->toBe(2);
});

it('ignora documentos manuais (Processado) — CA-09', function (): void {
    Documento::factory()->processado()->create();

    $this->artisan('extracao:run-parser')->assertSuccessful();
    $this->artisan('extracao:run-ia-cloud')->assertSuccessful();

    expect(Documento::query()->whereEstado(EstadoDocumento::Processado)->count())->toBe(1);
});

it('agenda os 5 comandos extracao:* com withoutOverlapping e as frequências correctas (RF-02)', function (): void {
    $eventos = collect(app(Schedule::class)->events())
        ->filter(fn ($evento): bool => str_contains((string) $evento->command, 'extracao:run-'))
        ->keyBy(fn ($evento): string => (string) preg_replace('/^.*(extracao:run-[a-z-]+).*$/', '$1', (string) $evento->command));

    expect($eventos)->toHaveCount(5);

    foreach (['extracao:run-scan', 'extracao:run-parser', 'extracao:run-tesseract', 'extracao:run-ia-local', 'extracao:run-ia-cloud'] as $comando) {
        expect($eventos->has($comando))->toBeTrue();
    }

    expect($eventos->get('extracao:run-scan')->expression)->toBe('* * * * *')
        ->and($eventos->get('extracao:run-ia-cloud')->expression)->toBe('*/5 * * * *');
});
