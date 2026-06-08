<?php

declare(strict_types=1);

use App\Models\User;

it('creates a user with factory', function (): void {
    $user = User::factory()->make();

    expect($user->name)->toBeString()
        ->and($user->email)->toBeString();
});

it('user has correct casts', function (): void {
    $user = new User;
    $casts = $user->getCasts();

    expect($casts)->toHaveKey('email_verified_at')
        ->and($casts)->toHaveKey('password');
});
