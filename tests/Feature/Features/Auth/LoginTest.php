<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devolve 200 com token quando credenciais são válidas', function (): void {
    User::factory()->create([
        'email' => 'utilizador@exemplo.pt',
        'password' => bcrypt('password-correcta'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'utilizador@exemplo.pt',
        'password' => 'password-correcta',
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token']])
        ->assertJsonPath('data.token', fn (mixed $token): bool => is_string($token) && $token !== '');
});

it('devolve 422 quando as credenciais são inválidas', function (): void {
    User::factory()->create(['email' => 'utilizador@exemplo.pt']);

    $this->postJson('/api/auth/login', [
        'email' => 'utilizador@exemplo.pt',
        'password' => 'password-errada',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('devolve 422 quando campos obrigatórios estão em falta', function (): void {
    $this->postJson('/api/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});
