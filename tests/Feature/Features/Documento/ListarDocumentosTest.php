<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['documentos'])->flush());
beforeEach(fn (): User => criarEAutenticarAdmin());

it('lista documentos paginados e devolve 200', function (): void {
    Documento::factory()->count(3)->processado()->create();

    $this->getJson('/api/documentos')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonCount(3, 'data');
});

it('omite deliberadamente fornecedor, cliente, categoria e histórico da listagem leve', function (): void {
    Documento::factory()->processado()->create();

    $this->getJson('/api/documentos')
        ->assertOk()
        ->assertJsonMissingPath('data.0.fornecedor')
        ->assertJsonMissingPath('data.0.cliente')
        ->assertJsonMissingPath('data.0.categoria')
        ->assertJsonMissingPath('data.0.historico');
});

it('filtra a listagem por estado', function (): void {
    Documento::factory()->count(2)->pendente()->create();
    Documento::factory()->count(3)->processado()->create();

    $this->getJson('/api/documentos?estado=PENDENTE')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.estado', 'PENDENTE');
});

it('rejeita um estado de filtro inválido com 422', function (): void {
    $this->getJson('/api/documentos?estado=INEXISTENTE')
        ->assertUnprocessable()
        ->assertJsonPath('detail', 'Os dados fornecidos são inválidos.');
});

it('utilizador com permissão de leitura lista e devolve 200', function (): void {
    criarEAutenticarUtilizador();

    $this->getJson('/api/documentos')->assertOk();
});

it('utilizador sem permissão de leitura recebe 403', function (): void {
    criarEAutenticarSemRole();

    $this->getJson('/api/documentos')->assertForbidden();
});
