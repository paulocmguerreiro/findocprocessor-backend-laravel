<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devolve 401 sem token', function (): void {
    $this->postJson('/api/auth/logout')
        ->assertUnauthorized();
});

it('devolve 204 e revoga token com autenticação válida', function (): void {
    $utilizador = User::factory()->create();
    $tokenResult = $utilizador->createToken('api', ['api']);

    expect($utilizador->tokens()->count())->toBe(1);

    $this->withToken($tokenResult->plainTextToken)
        ->postJson('/api/auth/logout')
        ->assertNoContent();

    expect($utilizador->tokens()->count())->toBe(0);
});
