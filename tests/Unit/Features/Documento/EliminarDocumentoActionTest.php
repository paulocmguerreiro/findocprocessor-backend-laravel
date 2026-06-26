<?php

declare(strict_types=1);

use App\Features\Documento\Eliminar\EliminarDocumentoAction;
use App\Models\Documento;
use App\Models\EtapaDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('processado');
    $this->actingAs(criarAdmin());
});

it('elimina o documento, o ficheiro e o histórico (cascade)', function (): void {
    $documento = Documento::factory()->processado()->create();
    EtapaDocumento::factory()->processado()->for($documento, 'documento')->create();
    Storage::disk('processado')->put($documento->nome_ficheiro_storage, 'conteudo');

    app(EliminarDocumentoAction::class)->handle($documento);

    $this->assertDatabaseMissing('documentos', ['id' => $documento->id]);
    $this->assertDatabaseCount('etapas_documento', 0);
    Storage::disk('processado')->assertMissing($documento->nome_ficheiro_storage);
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    $documento = Documento::factory()->processado()->create();
    auth()->logout();

    expect(fn () => app(EliminarDocumentoAction::class)->handle($documento))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('documentos', ['id' => $documento->id]);
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $documento = Documento::factory()->processado()->create();

        expect(fn () => app(EliminarDocumentoAction::class)->handle($documento))
            ->toThrow(AuthorizationException::class);

        $this->assertDatabaseHas('documentos', ['id' => $documento->id]);
    });
});
