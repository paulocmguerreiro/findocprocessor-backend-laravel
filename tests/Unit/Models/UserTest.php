<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

describe('Traits', function (): void {
    it('usa HasRoles', function (): void {
        expect(class_uses_recursive(User::class))->toHaveKey(HasRoles::class);
    });
});

describe('Roles e Permissions', function (): void {
    uses(RefreshDatabase::class);

    beforeEach(function (): void {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    });

    it('pode receber um role', function (): void {
        $role = Role::create(['name' => 'admin']);
        $utilizador = User::factory()->create();

        $utilizador->assignRole($role);

        expect($utilizador->hasRole('admin'))->toBeTrue();
    });

    it('tem permissão quando o role a inclui', function (): void {
        $permission = Permission::create(['name' => 'entidades.ver']);
        $role = Role::create(['name' => 'utilizador']);
        $role->givePermissionTo($permission);

        $utilizador = User::factory()->create();
        $utilizador->assignRole($role);

        expect($utilizador->hasPermissionTo('entidades.ver'))->toBeTrue();
    });

    it('não tem permissão quando o role não a inclui', function (): void {
        Role::create(['name' => 'utilizador']);
        Permission::create(['name' => 'entidades.criar']);

        $utilizador = User::factory()->create();
        $utilizador->assignRole('utilizador');

        expect($utilizador->hasPermissionTo('entidades.criar'))->toBeFalse();
    });
});
