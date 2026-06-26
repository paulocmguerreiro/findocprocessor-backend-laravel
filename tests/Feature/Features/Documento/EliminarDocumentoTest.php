<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('processado');
    Sanctum::actingAs(User::factory()->create(), ['api']);
});

it('elimina o documento e o ficheiro e devolve 204', function (): void {
    $documento = Documento::factory()->processado()->create();
    Storage::disk('processado')->put($documento->nome_ficheiro_storage, 'conteudo');

    $this->deleteJson("/api/documentos/{$documento->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('documentos', ['id' => $documento->id]);
    Storage::disk('processado')->assertMissing($documento->nome_ficheiro_storage);
});
