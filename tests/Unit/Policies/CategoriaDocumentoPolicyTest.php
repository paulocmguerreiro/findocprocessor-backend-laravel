<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

describe('Role admin', function (): void {
    beforeEach(function (): void {
        $this->utilizador = User::factory()->create();
        $this->utilizador->assignRole('admin');
    });

    it('pode viewAny', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('viewAny', CategoriaDocumento::class))->toBeTrue();
    });

    it('pode view', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('view', $categoria))->toBeTrue();
    });

    it('pode create', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('create', CategoriaDocumento::class))->toBeTrue();
    });

    it('pode update', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('update', $categoria))->toBeTrue();
    });

    it('pode delete', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('delete', $categoria))->toBeTrue();
    });
});

describe('Role utilizador', function (): void {
    beforeEach(function (): void {
        $this->utilizador = User::factory()->create();
        $this->utilizador->assignRole('utilizador');
    });

    it('pode viewAny', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('viewAny', CategoriaDocumento::class))->toBeTrue();
    });

    it('pode view', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('view', $categoria))->toBeTrue();
    });

    it('não pode create', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('create', CategoriaDocumento::class))->toBeFalse();
    });

    it('não pode update', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('update', $categoria))->toBeFalse();
    });

    it('não pode delete', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('delete', $categoria))->toBeFalse();
    });
});
