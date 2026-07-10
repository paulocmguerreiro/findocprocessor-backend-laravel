<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('aceita o token dentro da janela de expiração (8h)', function (): void {
    $utilizador = criarAdmin();
    $token = $utilizador->createToken('teste', ['api'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/categorias-documento')
        ->assertOk();
});

it('rejeita o token depois de expirar (> 8h)', function (): void {
    $utilizador = criarAdmin();
    $token = $utilizador->createToken('teste', ['api'])->plainTextToken;

    $this->travel(481)->minutes();

    $this->withToken($token)
        ->getJson('/api/categorias-documento')
        ->assertUnauthorized();
});
