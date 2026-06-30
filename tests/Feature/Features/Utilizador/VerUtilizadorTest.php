<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('admin vê outro utilizador e recebe 200 com estrutura correcta', function (): void {
    criarEAutenticarAdmin();
    $alvo = User::factory()->create();

    $this->getJson("/api/utilizadores/{$alvo->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $alvo->id)
        ->assertJsonPath('data.email', $alvo->email)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'roles', 'deleted_at', 'created_at']]);
});

it('admin vê utilizador inactivo (soft-deleted) e recebe 200', function (): void {
    criarEAutenticarAdmin();
    $alvo = User::factory()->inativo()->create();

    $this->getJson("/api/utilizadores/{$alvo->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $alvo->id);
});

it('utilizador sem permissão a ver OUTRO recebe 403', function (): void {
    criarEAutenticarUtilizador();
    $alvo = User::factory()->create();

    $this->getJson("/api/utilizadores/{$alvo->id}")
        ->assertForbidden();
});

it('utilizador sem permissão a ver O PRÓPRIO recebe 200 (auto-acesso)', function (): void {
    $proprio = criarEAutenticarUtilizador();

    $this->getJson("/api/utilizadores/{$proprio->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $proprio->id);
});

it('guest sem token recebe 401', function (): void {
    $alvo = User::factory()->create();

    $this->getJson("/api/utilizadores/{$alvo->id}")
        ->assertUnauthorized();
});
