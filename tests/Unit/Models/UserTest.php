<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $utilizador = User::factory()->create();
        $utilizador->assignRole('admin');

        expect($utilizador->hasRole('admin'))->toBeTrue();
    });

    it('admin tem permissão entidades.ver', function (): void {
        $utilizador = User::factory()->create();
        $utilizador->assignRole('admin');

        expect($utilizador->hasPermissionTo('entidades.ver'))->toBeTrue();
    });

    it('utilizador não tem permissão entidades.criar', function (): void {
        $utilizador = User::factory()->create();
        $utilizador->assignRole('utilizador');

        expect($utilizador->hasPermissionTo('entidades.criar'))->toBeFalse();
    });

    it('utilizador tem permissão entidades.ver', function (): void {
        $utilizador = User::factory()->create();
        $utilizador->assignRole('utilizador');

        expect($utilizador->hasPermissionTo('entidades.ver'))->toBeTrue();
    });
});
