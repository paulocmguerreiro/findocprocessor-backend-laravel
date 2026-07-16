<?php

declare(strict_types=1);

use App\Features\Documento\Transicao\RegraEliminarExtracaoTerminal;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('elimina a linha de ExtracaoDocumento quando o novo estado é terminal', function (EstadoDocumento $estado): void {
    $documento = Documento::factory()->create();
    ExtracaoDocumento::factory()->comDadosExtraidos()->for($documento, 'documento')->create();

    (new RegraEliminarExtracaoTerminal)->handle($documento, $estado);

    $this->assertDatabaseCount('extracoes_documento', 0);
})->with([
    'processado' => [EstadoDocumento::Processado],
    'erro' => [EstadoDocumento::Erro],
    'perigoso' => [EstadoDocumento::Perigoso],
]);

it('mantém a linha de ExtracaoDocumento quando o novo estado não é terminal', function (EstadoDocumento $estado): void {
    $documento = Documento::factory()->create();
    ExtracaoDocumento::factory()->for($documento, 'documento')->create();

    (new RegraEliminarExtracaoTerminal)->handle($documento, $estado);

    $this->assertDatabaseCount('extracoes_documento', 1);
})->with([
    'pendente' => [EstadoDocumento::Pendente],
    'análise malware' => [EstadoDocumento::AnaliseMalware],
    'análise texto' => [EstadoDocumento::AnaliseTexto],
    'análise ocr' => [EstadoDocumento::AnaliseOcr],
    'análise ia local' => [EstadoDocumento::AnaliseIaLocal],
    'análise cloud' => [EstadoDocumento::AnaliseCloud],
]);

it('não falha quando não existe linha de ExtracaoDocumento num estado terminal', function (): void {
    $documento = Documento::factory()->create();

    (new RegraEliminarExtracaoTerminal)->handle($documento, EstadoDocumento::Processado);

    $this->assertDatabaseCount('extracoes_documento', 0);
});
