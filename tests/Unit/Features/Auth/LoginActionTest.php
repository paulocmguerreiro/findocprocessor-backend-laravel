<?php

declare(strict_types=1);

use App\Features\Auth\Login\LoginAction;
use App\Features\Auth\Login\LoginDto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('devolve token quando credenciais são correctas', function (): void {
    $utilizador = User::factory()->create([
        'email' => 'utilizador@exemplo.pt',
        'password' => bcrypt('password-correcto'),
    ]);

    $token = app(LoginAction::class)->handle(new LoginDto(
        email: 'utilizador@exemplo.pt',
        password: 'password-correcto',
        ip: '127.0.0.1',
        agente: 'test',
    ));

    expect($token)->toBeString()->not->toBeEmpty();
    expect($utilizador->tokens()->count())->toBe(1);
});

it('lança ValidationException quando o email não existe', function (): void {
    expect(fn () => app(LoginAction::class)->handle(new LoginDto(
        email: 'inexistente@exemplo.pt',
        password: 'qualquer',
        ip: '127.0.0.1',
        agente: 'test',
    )))->toThrow(ValidationException::class);
});

it('mascara totalmente o email sem arroba nos logs', function (): void {
    Log::spy();

    expect(fn () => app(LoginAction::class)->handle(new LoginDto(
        email: 'email-sem-arroba',
        password: 'qualquer',
        ip: '127.0.0.1',
        agente: 'test',
    )))->toThrow(ValidationException::class);

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $mensagem, array $contexto): bool => $mensagem === 'auth.login.tentativa'
            && $contexto['email'] === '***'
    );
});

it('lança ValidationException quando a password está incorrecta', function (): void {
    User::factory()->create([
        'email' => 'utilizador@exemplo.pt',
        'password' => bcrypt('password-correcta'),
    ]);

    expect(fn () => app(LoginAction::class)->handle(new LoginDto(
        email: 'utilizador@exemplo.pt',
        password: 'password-errada',
        ip: '127.0.0.1',
        agente: 'test',
    )))->toThrow(ValidationException::class);
});
