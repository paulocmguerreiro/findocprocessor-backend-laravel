<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Models\EtapaDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn (): User => criarEAutenticarAdmin());

it('mostra o documento com o histórico e devolve 200', function (): void {
    $documento = Documento::factory()->processado()->create();
    EtapaDocumento::factory()->processado()->for($documento, 'documento')->create();

    $this->getJson("/api/documentos/{$documento->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $documento->id)
        ->assertJsonCount(1, 'data.historico')
        ->assertJsonPath('data.historico.0.estado', 'PROCESSADO');
});

it('devolve 404 para um documento inexistente', function (): void {
    $this->getJson('/api/documentos/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});

it('utilizador com permissão de leitura vê e devolve 200', function (): void {
    $documento = Documento::factory()->processado()->create();

    criarEAutenticarUtilizador();

    $this->getJson("/api/documentos/{$documento->id}")->assertOk();
});
