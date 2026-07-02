<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Restaurar\RestaurarCategoriaAction;
use App\Models\CategoriaDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['categorias_documento'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('restaura categoria inactiva recebendo CategoriaDocumento directamente', function (): void {
        $categoria = CategoriaDocumento::factory()->inativa()->create();

        $restaurada = app(RestaurarCategoriaAction::class)->handle($categoria);

        expect($restaurada)->toBeInstanceOf(CategoriaDocumento::class);
        $this->assertDatabaseHas('categorias_documento', ['id' => $categoria->id, 'deleted_at' => null]);
    });

    it('restaura categoria inactiva recebendo string UUID', function (): void {
        $categoria = CategoriaDocumento::factory()->inativa()->create();

        app(RestaurarCategoriaAction::class)->handle($categoria->id);

        $this->assertDatabaseHas('categorias_documento', ['id' => $categoria->id, 'deleted_at' => null]);
    });

    it('faz rollback quando ocorre excepção durante restauro', function (): void {
        $categoria = CategoriaDocumento::factory()->inativa()->create();

        CategoriaDocumento::restoring(function (): void {
            throw new RuntimeException('falha simulada durante restauro');
        });

        expect(fn () => app(RestaurarCategoriaAction::class)->handle($categoria))
            ->toThrow(RuntimeException::class, 'falha simulada durante restauro');

        $this->assertSoftDeleted('categorias_documento', ['id' => $categoria->id]);
    });
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão', function (): void {
        $categoria = CategoriaDocumento::factory()->inativa()->create();

        expect(fn () => app(RestaurarCategoriaAction::class)->handle($categoria))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $categoria = CategoriaDocumento::factory()->inativa()->create();

    expect(fn () => app(RestaurarCategoriaAction::class)->handle($categoria))
        ->toThrow(AuthorizationException::class);
});
