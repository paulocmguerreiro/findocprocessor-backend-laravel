<?php

declare(strict_types=1);

use App\Features\Documento\Processamento\RegistarFalhaTecnicaExtracaoAction;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
    Storage::fake('erro');
});

it('regista a falha, incrementa a tentativa e mantém o estado abaixo do tecto', function (): void {
    $documento = Documento::factory()->analiseTexto()->create();

    $resultado = app(RegistarFalhaTecnicaExtracaoAction::class)->handle($documento, 'motor rebentou');

    expect($resultado->estado)->toBe(EstadoDocumento::AnaliseTexto);
    $this->assertDatabaseHas('extracoes_documento', [
        'id_documento' => $documento->id,
        'extracao_tentativas' => 1,
    ]);
});

it('preserva o texto_extraido já registado ao contabilizar a falha técnica', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();
    ExtracaoDocumento::factory()->for($documento, 'documento')->create([
        'texto_extraido' => 'texto do parser',
        'extracao_tentativas' => 0,
    ]);

    app(RegistarFalhaTecnicaExtracaoAction::class)->handle($documento, 'timeout da IA');

    $this->assertDatabaseHas('extracoes_documento', [
        'id_documento' => $documento->id,
        'texto_extraido' => 'texto do parser',
        'extracao_tentativas' => 1,
    ]);
});

it('transiciona para Erro quando a tentativa atinge o tecto (max_tentativas)', function (): void {
    $documento = Documento::factory()->analiseOcr()->create();
    Storage::disk('entrada')->put($documento->nome_ficheiro_storage, 'conteudo');
    ExtracaoDocumento::factory()->comTentativas(config()->integer('extracao.max_tentativas') - 1)
        ->for($documento, 'documento')->create();

    $resultado = app(RegistarFalhaTecnicaExtracaoAction::class)->handle($documento, 'motor rebentou de novo');

    expect($resultado->estado)->toBe(EstadoDocumento::Erro);
});
