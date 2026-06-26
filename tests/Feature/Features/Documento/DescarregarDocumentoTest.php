<?php

declare(strict_types=1);

use App\Models\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('processado');
    criarEAutenticarAdmin();
});

it('descarrega o ficheiro do documento e devolve 200', function (): void {
    $documento = Documento::factory()->processado()->create(['nome_ficheiro_original' => 'fatura.pdf']);
    Storage::disk('processado')->put($documento->nome_ficheiro_storage, 'conteudo');

    $this->get("/api/documentos/{$documento->id}/ficheiro")
        ->assertOk()
        ->assertDownload('fatura.pdf');
});

it('devolve 404 quando o ficheiro não existe no disco', function (): void {
    $documento = Documento::factory()->processado()->create();

    $this->getJson("/api/documentos/{$documento->id}/ficheiro")
        ->assertNotFound();
});

it('utilizador com permissão de leitura descarrega e devolve 200', function (): void {
    $documento = Documento::factory()->processado()->create(['nome_ficheiro_original' => 'fatura.pdf']);
    Storage::disk('processado')->put($documento->nome_ficheiro_storage, 'conteudo');

    criarEAutenticarUtilizador();

    $this->get("/api/documentos/{$documento->id}/ficheiro")->assertOk();
});

it('utilizador sem permissão de leitura recebe 403', function (): void {
    $documento = Documento::factory()->processado()->create();
    Storage::disk('processado')->put($documento->nome_ficheiro_storage, 'conteudo');

    criarEAutenticarSemRole();

    $this->get("/api/documentos/{$documento->id}/ficheiro")->assertForbidden();
});
