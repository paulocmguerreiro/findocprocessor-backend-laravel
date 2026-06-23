<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('autenticado como admin', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('devolve 200 com id, nome e permissões do role', function (): void {
        $role = Role::findByName('admin', 'web');

        $this->getJson("/api/roles/{$role->id}")
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', fn (AssertableJson $data): AssertableJson => $data
                    ->where('id', $role->id)
                    ->where('nome', 'admin')
                    ->has('permissoes')
                )
            );
    });

    it('devolve 404 quando role não existe', function (): void {
        $this->getJson('/api/roles/99999')->assertNotFound();
    });
});

it('utilizador sem roles.ver recebe 403', function (): void {
    criarEAutenticarUtilizador();
    $role = Role::findByName('admin', 'web');

    $this->getJson("/api/roles/{$role->id}")->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $role = Role::findByName('admin', 'web');

    $this->getJson("/api/roles/{$role->id}")->assertUnauthorized();
});
