<?php

declare(strict_types=1);

use App\Features\Documento\Reprocessar\ModoReprocessamento;
use App\Models\Documento;
use App\Models\User;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('erro');
    Storage::fake('entrada');
    Sanctum::actingAs(User::factory()->create(), ['api']);
});

it('reprocessa um documento em Erro e devolve 200 em AguardaEnvio', function (): void {
    $documento = Documento::factory()->erro()->create();
    Storage::disk('erro')->put($documento->nome_ficheiro_storage, 'conteudo');

    $this->postJson("/api/documentos/{$documento->id}/reprocessar", ['modo' => ModoReprocessamento::Modelo->value])
        ->assertOk()
        ->assertJsonPath('data.status', EstadoDocumento::AguardaEnvio->value);

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
        ->assertJsonPath('detail', 'Transição de estado inválida: de "PROCESSADO" para "AGUARDA_ENVIO".');
});
