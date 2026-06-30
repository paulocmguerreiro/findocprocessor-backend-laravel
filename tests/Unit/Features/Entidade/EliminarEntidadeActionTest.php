<?php

declare(strict_types=1);

use App\Features\Entidade\Eliminar\EliminarEntidadeAction;
use App\Models\Entidade;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['entidades'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('elimina quando recebe Entidade directamente', function (): void {
        $entidade = Entidade::factory()->create();

        app(EliminarEntidadeAction::class)->handle($entidade);

        $this->assertSoftDeleted('entidades', ['id' => $entidade->id]);
    });

    it('elimina quando recebe string UUID', function (): void {
        $entidade = Entidade::factory()->create();

        app(EliminarEntidadeAction::class)->handle($entidade->id);

        $this->assertSoftDeleted('entidades', ['id' => $entidade->id]);
    });

    it('faz rollback quando ocorre excepção durante eliminação', function (): void {
        $entidade = Entidade::factory()->create();

        Entidade::deleting(function (): void {
            throw new RuntimeException('falha simulada durante eliminação');
        });

        expect(fn () => app(EliminarEntidadeAction::class)->handle($entidade))
            ->toThrow(RuntimeException::class, 'falha simulada durante eliminação');

        $this->assertDatabaseHas('entidades', ['id' => $entidade->id]);
    });

});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $entidade = Entidade::factory()->create();

        expect(fn () => app(EliminarEntidadeAction::class)->handle($entidade))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $entidade = Entidade::factory()->create();

    expect(fn () => app(EliminarEntidadeAction::class)->handle($entidade))
        ->toThrow(AuthorizationException::class);
});
