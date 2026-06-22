<?php

declare(strict_types=1);

use App\Features\Entidade\Ver\VerEntidadeAction;
use App\Models\Entidade;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('devolve o modelo quando recebe Entidade directamente', function (): void {
        $entidade = Entidade::factory()->create();

        $resultado = (new VerEntidadeAction)->handle($entidade);

        expect($resultado)->toBe($entidade);
    });

    it('resolve o modelo quando recebe string UUID', function (): void {
        $entidade = Entidade::factory()->create();

        $resultado = (new VerEntidadeAction)->handle($entidade->id);

        expect($resultado->id)->toBe($entidade->id);
    });
});

describe('sem permissão de leitura', function (): void {
    it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
        $entidade = Entidade::factory()->create();
        $this->actingAs(User::factory()->create()); // sem role — sem entidades.ver

        expect(fn (): Entidade => (new VerEntidadeAction)->handle($entidade))
            ->toThrow(AuthorizationException::class);
    });
});
