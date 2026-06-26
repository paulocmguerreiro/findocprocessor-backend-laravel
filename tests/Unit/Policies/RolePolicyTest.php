<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->role = Role::findByName('utilizador');
});

describe('config com permissão (admin)', function (): void {
    beforeEach(function (): void {
        $this->utilizador = User::factory()->create();
        $this->utilizador->assignRole('admin');
    });

    it('pode viewAny', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('viewAny', Role::class))->toBeTrue();
    });

    it('pode view', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('view', $this->role))->toBeTrue();
    });

    it('pode create', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('create', Role::class))->toBeTrue();
    });

    it('pode update', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('update', $this->role))->toBeTrue();
    });

    it('pode delete', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('delete', $this->role))->toBeTrue();
    });
});

describe('config sem permissão (utilizador — sem roles.*)', function (): void {
    beforeEach(function (): void {
        $this->utilizador = User::factory()->create();
        $this->utilizador->assignRole('utilizador');
    });

    it('não pode viewAny', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('viewAny', Role::class))->toBeFalse();
    });

    it('não pode view', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('view', $this->role))->toBeFalse();
    });

    it('não pode create', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('create', Role::class))->toBeFalse();
    });

    it('não pode update', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('update', $this->role))->toBeFalse();
    });

    it('não pode delete', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('delete', $this->role))->toBeFalse();
    });
});
