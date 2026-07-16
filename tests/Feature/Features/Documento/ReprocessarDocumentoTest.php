<?php

declare(strict_types=1);

use App\Features\Documento\Reprocessar\ModoReprocessamento;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('erro');
    Storage::fake('entrada');
    criarEAutenticarAdmin();
});

it('reprocessa um documento em Erro e devolve 200 em Pendente', function (): void {
    $documento = Documento::factory()->erro()->create();
    Storage::disk('erro')->put($documento->nome_ficheiro_storage, 'conteudo');

    $this->postJson("/api/documentos/{$documento->id}/reprocessar", ['modo' => ModoReprocessamento::Modelo->value])
        ->assertOk()
        ->assertJsonPath('data.estado', EstadoDocumento::Pendente->value);

    Storage::disk('entrada')->assertExists($documento->nome_ficheiro_storage);
});

it('rejeita um modo de reprocessamento inválido (422)', function (): void {
    $documento = Documento::factory()->erro()->create();

    $this->postJson("/api/documentos/{$documento->id}/reprocessar", ['modo' => 'INVALIDO'])
        ->assertUnprocessable();
});

it('rejeita o reprocessamento a partir de um estado inválido (422)', function (): void {
    $documento = Documento::factory()->processado()->create();

    $this->postJson("/api/documentos/{$documento->id}/reprocessar", ['modo' => ModoReprocessamento::Ferramenta->value])
        ->assertUnprocessable()
        ->assertJsonPath('detail', 'Transição de estado inválida: de "PROCESSADO" para "PENDENTE".');
});

it('reseta a extracoes_documento existente ao reprocessar', function (): void {
    $documento = Documento::factory()->erro()->create();
    Storage::disk('erro')->put($documento->nome_ficheiro_storage, 'conteudo');
    ExtracaoDocumento::factory()->for($documento, 'documento')->create([
        'extracao_tentativas' => 2,
        'texto_extraido' => 'texto anterior',
    ]);

    $this->postJson("/api/documentos/{$documento->id}/reprocessar", ['modo' => ModoReprocessamento::Modelo->value])
        ->assertOk();

    $extracao = ExtracaoDocumento::query()->where('id_documento', $documento->id)->sole();
    expect($extracao->extracao_tentativas)->toBe(0)
        ->and($extracao->texto_extraido)->toBeNull();
});

it('utilizador sem permissão de escrita recebe 403', function (): void {
    $documento = Documento::factory()->erro()->create();

    criarEAutenticarUtilizador();

    $this->postJson("/api/documentos/{$documento->id}/reprocessar", ['modo' => ModoReprocessamento::Modelo->value])
        ->assertForbidden();
});
