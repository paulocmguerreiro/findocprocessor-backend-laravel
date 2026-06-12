<?php

declare(strict_types=1);

use App\Shared\Http\ApiResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/test-sucesso', fn () => ApiResponse::devolverSucesso(new JsonResource(['id' => '1', 'nome' => 'Teste'])));
    Route::post('/test-criado', fn () => ApiResponse::devolverCriado(new JsonResource(['id' => '1', 'nome' => 'Teste'])));
    Route::delete('/test-vazio', fn () => ApiResponse::devolverVazio());
    Route::get('/test-coleccao', fn () => ApiResponse::devolverColeccao(
        JsonResource::collection(collect([['id' => '1'], ['id' => '2']])),
        ['total' => 2]
    ));
});

it('devolverSucesso devolve HTTP 200 com wrapper data', function (): void {
    $this->getJson('/test-sucesso')
        ->assertSuccessful()
        ->assertJsonStructure(['data'])
        ->assertJsonPath('data.id', '1');
});

it('devolverCriado devolve HTTP 201 com wrapper data', function (): void {
    $this->postJson('/test-criado')
        ->assertCreated()
        ->assertJsonStructure(['data'])
        ->assertJsonPath('data.id', '1');
});

it('devolverVazio devolve HTTP 204 sem corpo', function (): void {
    $this->deleteJson('/test-vazio')
        ->assertNoContent();
});

it('devolverColeccao devolve HTTP 200 com data e meta', function (): void {
    $this->getJson('/test-coleccao')
        ->assertSuccessful()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');
});
