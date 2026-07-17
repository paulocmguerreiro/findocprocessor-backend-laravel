<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
    $this->utilizador = criarEAutenticarAdmin();
});

it('recebe um upload e devolve 201 em Pendente', function (): void {
    $this->post('/api/documentos/upload', [
        'ficheiro' => UploadedFile::fake()->create('fatura.pdf', 100, 'application/pdf'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.estado', EstadoDocumento::Pendente->value);

    $documento = Documento::query()->firstOrFail();
    Storage::disk('entrada')->assertExists($documento->nome_ficheiro_storage);
    expect($documento->id_responsavel)->toBe($this->utilizador->id);
});

it('aceita uma imagem PNG válida', function (): void {
    $this->post('/api/documentos/upload', [
        'ficheiro' => UploadedFile::fake()->image('recibo.png'),
    ])->assertCreated();
});

it('rejeita um upload de tipo não permitido com 422', function (): void {
    $this->post('/api/documentos/upload', [
        'ficheiro' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
    ], ['Accept' => 'application/json'])->assertUnprocessable();
});

it('aceita imagens TIFF, BMP e WEBP', function (string $nome, string $mime): void {
    $this->post('/api/documentos/upload', [
        'ficheiro' => UploadedFile::fake()->create($nome, 100, $mime),
    ])->assertCreated();
})->with([
    'tiff' => ['scan.tiff', 'image/tiff'],
    'bmp' => ['scan.bmp', 'image/bmp'],
    'webp' => ['scan.webp', 'image/webp'],
]);

it('aceita um ficheiro de exactamente 50 MB', function (): void {
    $this->post('/api/documentos/upload', [
        'ficheiro' => UploadedFile::fake()->create('grande.pdf', 51200, 'application/pdf'),
    ])->assertCreated();
});

it('rejeita um ficheiro acima de 50 MB com 422', function (): void {
    $this->post('/api/documentos/upload', [
        'ficheiro' => UploadedFile::fake()->create('enorme.pdf', 51201, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ficheiro']);
});

it('aplica rate limit ao upload após 20 pedidos por minuto', function (): void {
    // ThrottleRequests está desligado por omissão nos testes (ver LoginThrottleTest).
    $this->withMiddleware(ThrottleRequests::class);

    // Imagens com dimensões distintas → conteúdo (e hash) único → sem colisão de duplicado.
    for ($i = 0; $i < 20; $i++) {
        $this->post('/api/documentos/upload', [
            'ficheiro' => UploadedFile::fake()->image("f{$i}.png", 10 + $i, 10 + $i),
        ], ['Accept' => 'application/json']);
    }

    $this->post('/api/documentos/upload', [
        'ficheiro' => UploadedFile::fake()->image('excedente.png', 200, 200),
    ], ['Accept' => 'application/json'])->assertStatus(429);
});

it('utilizador sem permissão de escrita recebe 403', function (): void {
    criarEAutenticarUtilizador();

    $this->post('/api/documentos/upload', [
        'ficheiro' => UploadedFile::fake()->create('fatura.pdf', 100, 'application/pdf'),
    ])->assertForbidden();

    $this->assertDatabaseCount('documentos', 0);
});
