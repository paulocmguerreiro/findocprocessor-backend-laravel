<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Eliminar\EliminarCategoriaAction;
use App\Models\CategoriaDocumento;
use App\Models\Documento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['categorias_documento'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('elimina definitivamente quando categoria não tem documentos associados', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        app(EliminarCategoriaAction::class)->handle($categoria);

        $this->assertDatabaseMissing('categorias_documento', ['id' => $categoria->id]);
    });

    it('faz soft delete quando categoria tem documentos associados', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        Documento::factory()->create(['id_categoria' => $categoria->id]);

        app(EliminarCategoriaAction::class)->handle($categoria);

        $this->assertSoftDeleted('categorias_documento', ['id' => $categoria->id]);
    });

    it('faz rollback quando ocorre excepção durante eliminação', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        CategoriaDocumento::deleting(function (): void {
            throw new RuntimeException('falha simulada durante eliminação');
        });

        expect(fn () => app(EliminarCategoriaAction::class)->handle($categoria))
            ->toThrow(RuntimeException::class, 'falha simulada durante eliminação');

        $this->assertDatabaseHas('categorias_documento', ['id' => $categoria->id]);
    });
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        expect(fn () => app(EliminarCategoriaAction::class)->handle($categoria))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $categoria = CategoriaDocumento::factory()->create();

    expect(fn () => app(EliminarCategoriaAction::class)->handle($categoria))
        ->toThrow(AuthorizationException::class);
});
