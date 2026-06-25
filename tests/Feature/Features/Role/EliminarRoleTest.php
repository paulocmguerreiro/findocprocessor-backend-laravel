<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Os seeds de roles deixam actividade persistente fora da transação do teste.
beforeEach(fn () => Activity::query()->delete());

describe('autenticado como admin', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('elimina role personalizado e devolve 204', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Activity::query()->delete();

        $this->deleteJson("/api/roles/{$role->id}")->assertNoContent();

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);

        expect(Activity::count())->toBe(1)
            ->and(Activity::query()->first()->event)->toBe('deleted');
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
    Activity::query()->delete();

    $this->deleteJson("/api/roles/{$role->id}")->assertForbidden();

    expect(Activity::count())->toBe(0);
});

it('guest sem token recebe 401', function (): void {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->deleteJson("/api/roles/{$role->id}")->assertUnauthorized();
});
