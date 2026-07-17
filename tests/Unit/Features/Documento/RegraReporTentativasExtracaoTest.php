<?php

declare(strict_types=1);

use App\Features\Documento\Operacoes\Transicao\RegraReporTentativasExtracao;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('repõe o contador a 0 quando avança para um estado não-terminal', function (EstadoDocumento $estado): void {
    $documento = Documento::factory()->create();
    ExtracaoDocumento::factory()->comTentativas(2)->for($documento, 'documento')->create();

    (new RegraReporTentativasExtracao)->handle($documento, $estado);

    $this->assertDatabaseHas('extracoes_documento', [
        'id_documento' => $documento->id,
        'extracao_tentativas' => 0,
    ]);
})->with([
    'pendente' => [EstadoDocumento::Pendente],
    'análise malware' => [EstadoDocumento::AnaliseMalware],
    'análise texto' => [EstadoDocumento::AnaliseTexto],
    'análise ocr' => [EstadoDocumento::AnaliseOcr],
    'análise ia local' => [EstadoDocumento::AnaliseIaLocal],
    'análise cloud' => [EstadoDocumento::AnaliseCloud],
]);

it('não repõe o contador numa transição para Erro (nem noutro terminal)', function (EstadoDocumento $estado): void {
    $documento = Documento::factory()->create();
    ExtracaoDocumento::factory()->comTentativas(3)->for($documento, 'documento')->create();

    (new RegraReporTentativasExtracao)->handle($documento, $estado);

    $this->assertDatabaseHas('extracoes_documento', [
        'id_documento' => $documento->id,
        'extracao_tentativas' => 3,
    ]);
})->with([
    'erro' => [EstadoDocumento::Erro],
    'processado' => [EstadoDocumento::Processado],
    'perigoso' => [EstadoDocumento::Perigoso],
]);

it('não falha quando não existe linha de ExtracaoDocumento', function (): void {
    $documento = Documento::factory()->create();

    (new RegraReporTentativasExtracao)->handle($documento, EstadoDocumento::AnaliseOcr);

    $this->assertDatabaseCount('extracoes_documento', 0);
});
