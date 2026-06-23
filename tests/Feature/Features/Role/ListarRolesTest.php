<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

describe('autenticado como admin', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('lista roles e devolve 200 com permissões', function (): void {
        $this->getJson('/api/roles')
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data')
                ->has('meta')
                ->etc()
            );
    });
});

it('utilizador sem roles.ver recebe 403', function (): void {
    criarEAutenticarUtilizador();

    $this->getJson('/api/roles')->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $this->getJson('/api/roles')->assertUnauthorized();
});
