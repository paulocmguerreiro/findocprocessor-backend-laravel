<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
    criarEAutenticarAdmin();
});

it('recebe um upload e devolve 201 em Pendente', function (): void {
    $this->post('/api/documentos/upload', [
        'ficheiro' => UploadedFile::fake()->create('fatura.pdf', 100, 'application/pdf'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', EstadoDocumento::Pendente->value);

    $documento = Documento::query()->firstOrFail();
    Storage::disk('entrada')->assertExists($documento->nome_ficheiro_storage);
});

it('rejeita um upload de tipo não permitido com 422', function (): void {
    $this->post('/api/documentos/upload', [
        'ficheiro' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
    ], ['Accept' => 'application/json'])->assertUnprocessable();
});

it('utilizador sem permissão de escrita recebe 403', function (): void {
    criarEAutenticarUtilizador();

    $this->post('/api/documentos/upload', [
        'ficheiro' => UploadedFile::fake()->create('fatura.pdf', 100, 'application/pdf'),
    ])->assertForbidden();

    $this->assertDatabaseCount('documentos', 0);
});
