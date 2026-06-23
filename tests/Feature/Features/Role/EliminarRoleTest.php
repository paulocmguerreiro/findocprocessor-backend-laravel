<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('autenticado como admin', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('elimina role personalizado e devolve 204', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->deleteJson("/api/roles/{$role->id}")->assertNoContent();

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);
    });

    it('devolve 422 ao tentar eliminar role admin', function (): void {
        $role = Role::findByName('admin', 'web');

        $this->deleteJson("/api/roles/{$role->id}")->assertUnprocessable();

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
    });

    it('devolve 404 quando role não existe', function (): void {
        $this->deleteJson('/api/roles/99999')->assertNotFound();
    });
});

it('utilizador sem roles.eliminar recebe 403', function (): void {
    criarEAutenticarUtilizador();
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->deleteJson("/api/roles/{$role->id}")->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->deleteJson("/api/roles/{$role->id}")->assertUnauthorized();
});
