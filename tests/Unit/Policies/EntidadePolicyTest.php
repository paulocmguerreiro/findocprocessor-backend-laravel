<?php

declare(strict_types=1);

use App\Models\Entidade;
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
        expect(Gate::forUser($this->utilizador)->allows('viewAny', Entidade::class))->toBeTrue();
    });

    it('pode view', function (): void {
        $entidade = Entidade::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('view', $entidade))->toBeTrue();
    });

    it('pode create', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('create', Entidade::class))->toBeTrue();
    });

    it('pode update', function (): void {
        $entidade = Entidade::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('update', $entidade))->toBeTrue();
    });

    it('pode delete', function (): void {
        $entidade = Entidade::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('delete', $entidade))->toBeTrue();
    });

    it('pode restore', function (): void {
        $entidade = Entidade::factory()->inativa()->create();

        expect(Gate::forUser($this->utilizador)->allows('restore', $entidade))->toBeTrue();
    });
});

describe('Role utilizador', function (): void {
    beforeEach(function (): void {
        $this->utilizador = User::factory()->create();
        $this->utilizador->assignRole('utilizador');
    });

    it('pode viewAny', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('viewAny', Entidade::class))->toBeTrue();
    });

    it('pode view', function (): void {
        $entidade = Entidade::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('view', $entidade))->toBeTrue();
    });

    it('não pode create', function (): void {
        expect(Gate::forUser($this->utilizador)->allows('create', Entidade::class))->toBeFalse();
    });

    it('não pode update', function (): void {
        $entidade = Entidade::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('update', $entidade))->toBeFalse();
    });

    it('não pode delete', function (): void {
        $entidade = Entidade::factory()->create();

        expect(Gate::forUser($this->utilizador)->allows('delete', $entidade))->toBeFalse();
    });

    it('não pode restore', function (): void {
        $entidade = Entidade::factory()->inativa()->create();

        expect(Gate::forUser($this->utilizador)->allows('restore', $entidade))->toBeFalse();
    });
});
