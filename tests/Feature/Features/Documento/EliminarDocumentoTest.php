<?php

declare(strict_types=1);

use App\Models\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('processado');
    criarEAutenticarAdmin();
});

it('elimina o documento e o ficheiro e devolve 204', function (): void {
    $documento = Documento::factory()->processado()->create();
    Storage::disk('processado')->put($documento->nome_ficheiro_storage, 'conteudo');

    $this->deleteJson("/api/documentos/{$documento->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('documentos', ['id' => $documento->id]);
    Storage::disk('processado')->assertMissing($documento->nome_ficheiro_storage);
});

it('utilizador sem permissão de escrita recebe 403', function (): void {
    $documento = Documento::factory()->processado()->create();

    criarEAutenticarUtilizador();

    $this->deleteJson("/api/documentos/{$documento->id}")->assertForbidden();

    $this->assertDatabaseHas('documentos', ['id' => $documento->id]);
});
