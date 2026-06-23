<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

describe('autenticado como admin', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('cria role e devolve 201 com o recurso', function (): void {
        $this->postJson('/api/roles', [
            'nome' => 'editor',
            'permissoes' => ['entidades.ver'],
        ])
            ->assertCreated()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', fn (AssertableJson $data): AssertableJson => $data
                    ->whereType('id', 'integer')
                    ->where('nome', 'editor')
                    ->has('permissoes')
                )
            );

        $this->assertDatabaseHas('roles', ['name' => 'editor']);
    });

    it('devolve 422 quando nome já existe', function (): void {
        $this->postJson('/api/roles', [
            'nome' => 'admin',
            'permissoes' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['nome']);
    });

    it('devolve 422 quando permissão não existe', function (): void {
        $this->postJson('/api/roles', [
            'nome' => 'editor',
            'permissoes' => ['permissao.inexistente'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['permissoes.0']);
    });

    it('devolve 422 quando nome está em falta', function (): void {
        $this->postJson('/api/roles', [
            'permissoes' => ['entidades.ver'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['nome']);
    });
});

it('utilizador sem roles.criar recebe 403', function (): void {
    criarEAutenticarUtilizador();

    $this->postJson('/api/roles', ['nome' => 'editor', 'permissoes' => []])
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $this->postJson('/api/roles', ['nome' => 'editor', 'permissoes' => []])
        ->assertUnauthorized();
});
