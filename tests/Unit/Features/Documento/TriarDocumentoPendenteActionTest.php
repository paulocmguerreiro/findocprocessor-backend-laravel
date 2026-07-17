<?php

declare(strict_types=1);

use App\Features\Documento\Atribuicao\Triar\TriarDocumentoPendenteAction;
use App\Infrastructure\Malware\ContratoAnalisadorMalware;
use App\Infrastructure\Malware\FalhaAnaliseMalwareException;
use App\Infrastructure\Malware\ResultadoAnaliseMalware;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Transição de sistema (pipeline): corre sem utilizador autenticado, sem Gate.
beforeEach(function (): void {
    Storage::fake('entrada');
    Storage::fake('perigoso');
    Storage::fake('erro');
});

function criarDocumentoPendenteComFicheiro(): Documento
{
    $documento = Documento::factory()->pendente()->create();
    Storage::disk('entrada')->put($documento->nome_ficheiro_storage, 'conteudo');

    return $documento;
}

it('admite o documento a AnaliseMalware antes de correr o scan', function (): void {
    $documento = criarDocumentoPendenteComFicheiro();

    app()->instance(ContratoAnalisadorMalware::class, Mockery::mock(ContratoAnalisadorMalware::class, function ($mock): void {
        $mock->shouldReceive('analisar')->once()->andReturn(ResultadoAnaliseMalware::limpo());
    }));

    app(TriarDocumentoPendenteAction::class)->handle($documento);

    // A passagem intermédia por AnaliseMalware fica registada no histórico.
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::AnaliseMalware->value,
        'motivo' => 'triagem de malware',
    ]);
});

it('transiciona para Perigoso quando o ficheiro está infectado', function (): void {
    $documento = criarDocumentoPendenteComFicheiro();

    app()->instance(ContratoAnalisadorMalware::class, Mockery::mock(ContratoAnalisadorMalware::class, function ($mock): void {
        $mock->shouldReceive('analisar')->once()->andReturn(ResultadoAnaliseMalware::infectado('Eicar-Signature'));
    }));

    $resultado = app(TriarDocumentoPendenteAction::class)->handle($documento);

    expect($resultado->estado)->toBe(EstadoDocumento::Perigoso)
        ->and($resultado->disco_storage)->toBe('perigoso');

    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Perigoso->value,
        'motivo' => 'Eicar-Signature',
    ]);
});

it('transiciona para AnaliseTexto quando o ficheiro está limpo', function (): void {
    $documento = criarDocumentoPendenteComFicheiro();

    app()->instance(ContratoAnalisadorMalware::class, Mockery::mock(ContratoAnalisadorMalware::class, function ($mock): void {
        $mock->shouldReceive('analisar')->once()->andReturn(ResultadoAnaliseMalware::limpo());
    }));

    $resultado = app(TriarDocumentoPendenteAction::class)->handle($documento);

    expect($resultado->estado)->toBe(EstadoDocumento::AnaliseTexto)
        ->and($resultado->disco_storage)->toBe('entrada');

    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::AnaliseTexto->value,
        'motivo' => 'análise de malware concluída',
    ]);
});

it('transiciona para AnaliseTexto com motivo "scan desligado" quando a camada não está configurada', function (): void {
    $documento = criarDocumentoPendenteComFicheiro();

    app()->instance(ContratoAnalisadorMalware::class, Mockery::mock(ContratoAnalisadorMalware::class, function ($mock): void {
        $mock->shouldReceive('analisar')->once()->andReturn(ResultadoAnaliseMalware::naoConfigurado());
    }));

    $resultado = app(TriarDocumentoPendenteAction::class)->handle($documento);

    expect($resultado->estado)->toBe(EstadoDocumento::AnaliseTexto);

    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::AnaliseTexto->value,
        'motivo' => 'scan de malware desligado',
    ]);
});

it('transiciona para Erro quando o scan falha', function (): void {
    $documento = criarDocumentoPendenteComFicheiro();

    app()->instance(ContratoAnalisadorMalware::class, Mockery::mock(ContratoAnalisadorMalware::class, function ($mock): void {
        $mock->shouldReceive('analisar')->once()->andThrow(new FalhaAnaliseMalwareException('timeout do clamd'));
    }));

    $resultado = app(TriarDocumentoPendenteAction::class)->handle($documento);

    expect($resultado->estado)->toBe(EstadoDocumento::Erro)
        ->and($resultado->disco_storage)->toBe('erro');

    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Erro->value,
        'motivo' => 'timeout do clamd',
    ]);
});
