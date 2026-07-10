<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('regista tentativa e sucesso quando login é bem-sucedido', function (): void {
    Log::spy();

    User::factory()->create([
        'email' => 'utilizador@exemplo.pt',
        'password' => bcrypt('password-correcta'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'utilizador@exemplo.pt',
        'password' => 'password-correcta',
    ])->assertOk();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $msg): bool => $msg === 'auth.login.tentativa');

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $msg): bool => $msg === 'auth.login.sucesso');
});

it('regista o email mascarado, nunca em claro', function (): void {
    Log::spy();

    User::factory()->create([
        'email' => 'utilizador@exemplo.pt',
        'password' => bcrypt('password-correcta'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'utilizador@exemplo.pt',
        'password' => 'password-correcta',
    ])->assertOk();

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $mensagem, array $contexto): bool => $mensagem === 'auth.login.tentativa'
            && $contexto['email'] === 'u***@exemplo.pt'
    );

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $mensagem, array $contexto): bool => $mensagem === 'auth.login.sucesso'
            && $contexto['email'] === 'u***@exemplo.pt'
    );
});

it('regista tentativa e falhou quando credenciais são inválidas', function (): void {
    Log::spy();

    User::factory()->create(['email' => 'utilizador@exemplo.pt']);

    $this->postJson('/api/auth/login', [
        'email' => 'utilizador@exemplo.pt',
        'password' => 'password-errada',
    ])->assertUnprocessable();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $msg): bool => $msg === 'auth.login.tentativa');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $msg): bool => $msg === 'auth.login.falhou');
});
