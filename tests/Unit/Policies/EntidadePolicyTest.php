<?php

declare(strict_types=1);

use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

describe('Utilizador autenticado', function (): void {
    beforeEach(function (): void {
        $this->utilizador = User::factory()->create();
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
});

describe('Guest (policy placeholder — sem restrições)', function (): void {
    it('viewAny não é bloqueado', function (): void {
        expect(Gate::forUser(null)->allows('viewAny', Entidade::class))->toBeTrue();
    });

    it('view não é bloqueado', function (): void {
        $entidade = Entidade::factory()->create();

        expect(Gate::forUser(null)->allows('view', $entidade))->toBeTrue();
    });

    it('create não é bloqueado', function (): void {
        expect(Gate::forUser(null)->allows('create', Entidade::class))->toBeTrue();
    });

    it('update não é bloqueado', function (): void {
        $entidade = Entidade::factory()->create();

        expect(Gate::forUser(null)->allows('update', $entidade))->toBeTrue();
    });

    it('delete não é bloqueado', function (): void {
        $entidade = Entidade::factory()->create();

        expect(Gate::forUser(null)->allows('delete', $entidade))->toBeTrue();
    });
});
