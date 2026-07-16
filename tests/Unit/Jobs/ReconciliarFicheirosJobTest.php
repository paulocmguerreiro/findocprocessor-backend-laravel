<?php

declare(strict_types=1);

use App\Jobs\ReconciliarFicheirosJob;
use App\Models\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
    Storage::fake('enviado');
    Storage::fake('processado');
    Storage::fake('erro');
    Storage::fake('perigoso');
});

function correrReconciliarFicheirosJob(): void
{
    app()->call([app(ReconciliarFicheirosJob::class), 'handle']);
}

it('ignora um documento preso cujo ficheiro está no disco correcto (coerente)', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create(['updated_at' => now()->subMinutes(30)]);
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');

    correrReconciliarFicheirosJob();

    expect($documento->fresh()->disco_storage)->toBe('enviado')
        ->and($documento->fresh()->nome_ficheiro_storage)->toBe($documento->nome_ficheiro_storage);
});

it('repõe disco_storage/nome_ficheiro_storage quando o ficheiro está localizado noutro disco', function (): void {
    $conteudo = 'conteudo-real-do-ficheiro';
    $documento = Documento::factory()->analiseIaLocal()->create([
        'hash_sha256' => hash('sha256', $conteudo),
        'updated_at' => now()->subMinutes(30),
    ]);
    Storage::disk('erro')->put($documento->nome_ficheiro_storage, $conteudo);

    correrReconciliarFicheirosJob();

    $documento->refresh();
    expect($documento->disco_storage)->toBe('erro')
        ->and($documento->nome_ficheiro_storage)->toBe($documento->nome_ficheiro_storage);
});

it('regista erro estruturado e não altera a BD quando o ficheiro não é encontrado em nenhum disco', function (): void {
    Log::spy();

    $documento = Documento::factory()->analiseIaLocal()->create(['updated_at' => now()->subMinutes(30)]);

    correrReconciliarFicheirosJob();

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $mensagem, array $contexto): bool => $contexto['id_documento'] === $documento->id);

    expect($documento->fresh()->disco_storage)->toBe('enviado')
        ->and($documento->fresh()->nome_ficheiro_storage)->toBe($documento->nome_ficheiro_storage);
});

it('não toca em documentos dentro da janela do limiar', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create(['updated_at' => now()->subMinutes(5)]);

    correrReconciliarFicheirosJob();

    expect($documento->fresh()->updated_at->equalTo($documento->updated_at))->toBeTrue();
});

it('reconcilia também um documento preso num estado transitório do disco entrada (AnaliseMalware)', function (): void {
    $documento = Documento::factory()->analiseMalware()->create(['updated_at' => now()->subMinutes(30)]);
    Storage::disk('entrada')->put($documento->nome_ficheiro_storage, 'conteudo');

    correrReconciliarFicheirosJob();

    expect($documento->fresh()->disco_storage)->toBe('entrada')
        ->and($documento->fresh()->nome_ficheiro_storage)->toBe($documento->nome_ficheiro_storage);
});

it('não toca em documentos fora dos estados transitórios', function (): void {
    $documento = Documento::factory()->pendente()->create(['updated_at' => now()->subMinutes(30)]);

    correrReconciliarFicheirosJob();

    expect($documento->fresh()->updated_at->equalTo($documento->updated_at))->toBeTrue();
});
