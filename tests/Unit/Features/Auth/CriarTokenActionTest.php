<?php

declare(strict_types=1);

use App\Features\Auth\CriarToken\CriarTokenAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cria token com nome e ability api e devolve string', function (): void {
    $utilizador = User::factory()->create();

    $token = app(CriarTokenAction::class)->handle($utilizador, 'token-integracao');

    expect($token)->toBeString()->not->toBeEmpty();
    expect($utilizador->tokens()->count())->toBe(1);
    expect($utilizador->tokens()->first()->name)->toBe('token-integracao');
    expect($utilizador->tokens()->first()->abilities)->toBe(['api']);
});
