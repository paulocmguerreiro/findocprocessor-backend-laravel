<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(User::factory()->create(), ['api']));

it('lista documentos paginados e devolve 200', function (): void {
    Documento::factory()->count(3)->processado()->create();

    $this->getJson('/api/documentos')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonCount(3, 'data');
});

it('filtra a listagem por estado', function (): void {
    Documento::factory()->count(2)->pendente()->create();
    Documento::factory()->count(3)->processado()->create();

    $this->getJson('/api/documentos?estado=PENDENTE')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'PENDENTE');
});

it('rejeita um estado de filtro inválido com 422', function (): void {
    $this->getJson('/api/documentos?estado=INEXISTENTE')
        ->assertUnprocessable()
        ->assertJsonPath('detail', 'Os dados fornecidos são inválidos.');
});
