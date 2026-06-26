<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->alvo = User::factory()->create();
});

describe('config com permissão (admin)', function (): void {
    beforeEach(function (): void {
        $this->utilizador = User::factory()->create();
        $this->utilizador->assignRole('admin');
    });

    it('pode atribuirRole', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('atribuirRole', $this->alvo))->toBeTrue();
    });
});

describe('config sem permissão (utilizador)', function (): void {
    beforeEach(function (): void {
        $this->utilizador = User::factory()->create();
        $this->utilizador->assignRole('utilizador');
    });

    it('não pode atribuirRole', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('atribuirRole', $this->alvo))->toBeFalse();
    });
});
