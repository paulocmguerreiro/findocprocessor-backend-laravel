<?php

declare(strict_types=1);

use App\Features\Entidade\Restaurar\RestaurarEntidadeAction;
use App\Models\Entidade;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['entidades'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('restaura entidade inativa quando recebe Entidade directamente', function (): void {
        $entidade = Entidade::factory()->inativa()->create();

        $restaurada = app(RestaurarEntidadeAction::class)->handle($entidade);

        expect($restaurada)->toBeInstanceOf(Entidade::class);
        $this->assertNotSoftDeleted('entidades', ['id' => $entidade->id]);
    });

    it('restaura entidade inativa quando recebe string UUID', function (): void {
        $entidade = Entidade::factory()->inativa()->create();

        app(RestaurarEntidadeAction::class)->handle($entidade->id);

        $this->assertNotSoftDeleted('entidades', ['id' => $entidade->id]);
    });

    it('lança ModelNotFoundException quando UUID não existe', function (): void {
        expect(fn () => app(RestaurarEntidadeAction::class)->handle('00000000-0000-0000-0000-000000000000'))
            ->toThrow(ModelNotFoundException::class);
    });

    it('faz rollback quando ocorre excepção durante restauro', function (): void {
        $entidade = Entidade::factory()->inativa()->create();

        Entidade::restoring(function (): void {
            throw new RuntimeException('falha simulada durante restauro');
        });

        expect(fn () => app(RestaurarEntidadeAction::class)->handle($entidade))
            ->toThrow(RuntimeException::class, 'falha simulada durante restauro');

        $this->assertSoftDeleted('entidades', ['id' => $entidade->id]);
    });
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão', function (): void {
        $entidade = Entidade::factory()->inativa()->create();

        expect(fn () => app(RestaurarEntidadeAction::class)->handle($entidade))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $entidade = Entidade::factory()->inativa()->create();

    expect(fn () => app(RestaurarEntidadeAction::class)->handle($entidade))
        ->toThrow(AuthorizationException::class);
});
