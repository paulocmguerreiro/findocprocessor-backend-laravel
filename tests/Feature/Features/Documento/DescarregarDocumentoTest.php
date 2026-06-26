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
