<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

it('bloqueia com 429 após exceder o limite de tentativas de login', function (): void {
    $this->withMiddleware(ThrottleRequests::class);

    // Email único: garante um contador de rate limit fresco (a cache Redis é partilhada).
    $email = 'brute-'.uniqid().'@exemplo.pt';
    User::factory()->create(['email' => $email]);

    $credenciais = ['email' => $email, 'password' => 'password-errada'];

    // As 5 primeiras tentativas passam o throttle (credenciais inválidas → 422).
    for ($tentativa = 1; $tentativa <= 5; $tentativa++) {
        $this->postJson('/api/auth/login', $credenciais)->assertUnprocessable();
    }

    // A 6.ª excede o limite (5/min) → 429.
    $this->postJson('/api/auth/login', $credenciais)
        ->assertStatus(429)
        ->assertJsonPath('detail', 'Demasiados pedidos. Tente novamente mais tarde.');
});
