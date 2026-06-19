<?php

declare(strict_types=1);

use App\Features\Auth\Logout\LogoutAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('revoga o token actual do utilizador', function (): void {
    $utilizador = User::factory()->create();
    $tokenResult = $utilizador->createToken('api', ['api']);

    $utilizador->withAccessToken($tokenResult->accessToken);

    expect($utilizador->tokens()->count())->toBe(1);

    app(LogoutAction::class)->handle($utilizador);

    expect($utilizador->tokens()->count())->toBe(0);
});
