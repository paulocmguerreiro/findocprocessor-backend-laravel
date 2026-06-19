<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('devolve 401 sem autenticação', function (): void {
    $this->postJson('/api/auth/tokens', ['nome_token' => 'integracao'])
        ->assertUnauthorized();
});

it('devolve 200 com token quando autenticado', function (): void {
    $utilizador = User::factory()->create();
    Sanctum::actingAs($utilizador, ['api']);

    $this->postJson('/api/auth/tokens', ['nome_token' => 'integracao'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token']])
        ->assertJsonPath('data.token', fn (mixed $token): bool => is_string($token) && $token !== '');

    expect($utilizador->tokens()->count())->toBe(1);
});

it('devolve 422 quando nome_token está em falta', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['api']);

    $this->postJson('/api/auth/tokens', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nome_token']);
});
