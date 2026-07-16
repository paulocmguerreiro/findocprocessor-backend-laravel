<?php

declare(strict_types=1);

use App\Features\Documento\Transicao\RegraReconciliarLocalizacaoFicheiro;
use App\Models\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
    Storage::fake('enviado');
    Storage::fake('processado');
    Storage::fake('erro');
    Storage::fake('perigoso');
});

it('devolve coerente quando o ficheiro está no disco esperado', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');

    $resultado = app(RegraReconciliarLocalizacaoFicheiro::class)->handle($documento);

    expect($resultado->coerente)->toBeTrue()
        ->and($resultado->encontrado)->toBeTrue()
        ->and($resultado->disco)->toBe('enviado')
        ->and($resultado->nome)->toBe($documento->nome_ficheiro_storage);
});

it('localiza o ficheiro noutro disco conhecido por hash quando ausente do disco esperado', function (): void {
    $conteudo = 'conteudo-real-do-ficheiro';
    $documento = Documento::factory()->analiseIaLocal()->create(['hash_sha256' => hash('sha256', $conteudo)]);
    Storage::disk('erro')->put($documento->nome_ficheiro_storage, $conteudo);

    $resultado = app(RegraReconciliarLocalizacaoFicheiro::class)->handle($documento);

    expect($resultado->coerente)->toBeFalse()
        ->and($resultado->encontrado)->toBeTrue()
        ->and($resultado->disco)->toBe('erro')
        ->and($resultado->nome)->toBe($documento->nome_ficheiro_storage);
});

it('devolve não encontrado quando o ficheiro não existe em nenhum disco conhecido', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();

    $resultado = app(RegraReconciliarLocalizacaoFicheiro::class)->handle($documento);

    expect($resultado->coerente)->toBeFalse()
        ->and($resultado->encontrado)->toBeFalse()
        ->and($resultado->disco)->toBeNull()
        ->and($resultado->nome)->toBeNull();
});
