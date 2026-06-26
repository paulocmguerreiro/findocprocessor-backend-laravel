<?php

declare(strict_types=1);

use App\Models\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exige autenticação nos endpoints de Documento (401 sem token)', function (string $metodo, string $caminho): void {
    $this->json($metodo, $caminho)->assertUnauthorized();
})->with([
    'listar' => ['GET', '/api/documentos'],
    'registar' => ['POST', '/api/documentos'],
    'upload' => ['POST', '/api/documentos/upload'],
    'ver' => ['GET', '/api/documentos/00000000-0000-0000-0000-000000000000'],
    'eliminar' => ['DELETE', '/api/documentos/00000000-0000-0000-0000-000000000000'],
]);

it('não eliminou nada sem autenticação', function (): void {
    $documento = Documento::factory()->processado()->create();

    $this->deleteJson("/api/documentos/{$documento->id}")->assertUnauthorized();

    $this->assertDatabaseHas('documentos', ['id' => $documento->id]);
});
