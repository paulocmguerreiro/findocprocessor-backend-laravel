<?php

declare(strict_types=1);

use App\Features\Utilizador\Ver\VerUtilizadorAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('com permissão devolve o utilizador com roles carregados', function (): void {
    $this->actingAs(criarAdmin());
    $alvo = User::factory()->create();

    $resultado = app(VerUtilizadorAction::class)->handle($alvo);

    expect($resultado->is($alvo))->toBeTrue()
        ->and($resultado->relationLoaded('roles'))->toBeTrue();
});

it('sem permissão a ver outro lança AuthorizationException', function (): void {
    $this->actingAs(criarUtilizador());
    $alvo = User::factory()->create();

    expect(fn (): User => app(VerUtilizadorAction::class)->handle($alvo))
        ->toThrow(AuthorizationException::class);
});

it('o próprio utilizador (sem utilizadores.ver) consegue ver-se', function (): void {
    $proprio = criarUtilizador();
    $this->actingAs($proprio);

    $resultado = app(VerUtilizadorAction::class)->handle($proprio);

    expect($resultado->is($proprio))->toBeTrue();
});
