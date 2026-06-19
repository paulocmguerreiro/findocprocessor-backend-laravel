<?php

declare(strict_types=1);

use App\Features\Auth\Login\LoginAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('devolve token quando credenciais são correctas', function (): void {
    $utilizador = User::factory()->create([
        'email' => 'utilizador@exemplo.pt',
        'password' => bcrypt('password-correcto'),
    ]);

    $token = app(LoginAction::class)->handle('utilizador@exemplo.pt', 'password-correcto');

    expect($token)->toBeString()->not->toBeEmpty();
    expect($utilizador->tokens()->count())->toBe(1);
});

it('lança ValidationException quando o email não existe', function (): void {
    expect(fn () => app(LoginAction::class)->handle('inexistente@exemplo.pt', 'qualquer'))
        ->toThrow(ValidationException::class);
});

it('lança ValidationException quando a password está incorrecta', function (): void {
    User::factory()->create([
        'email' => 'utilizador@exemplo.pt',
        'password' => bcrypt('password-correcta'),
    ]);

    expect(fn () => app(LoginAction::class)->handle('utilizador@exemplo.pt', 'password-errada'))
        ->toThrow(ValidationException::class);
});
