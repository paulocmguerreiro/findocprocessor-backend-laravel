<?php

declare(strict_types=1);

use App\Features\TipoDocumento\Listar\CampoOrdenacaoTiposDocumento;
use App\Features\TipoDocumento\Listar\ListarTiposDocumentoAction;
use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['tipos_documento'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('devolve lista vazia quando não existem tipos de documento', function (): void {
        $resultado = app(ListarTiposDocumentoAction::class)->handle(15, CampoOrdenacaoTiposDocumento::Nome, DirecaoOrdenacao::Asc);

        expect($resultado->count())->toBe(0);
    });

    it('devolve tipos de documento ordenados por nome ascendente', function (): void {
        TipoDocumento::factory()->create(['nome' => 'Zeta']);
        TipoDocumento::factory()->create(['nome' => 'Alfa']);
        TipoDocumento::factory()->create(['nome' => 'Beta']);

        $resultado = app(ListarTiposDocumentoAction::class)->handle(15, CampoOrdenacaoTiposDocumento::Nome, DirecaoOrdenacao::Asc);

        expect($resultado->pluck('nome')->all())->toBe(['Alfa', 'Beta', 'Zeta']);
    });

    it('filtra por id_categoria quando indicado', function (): void {
        $categoriaA = CategoriaDocumento::factory()->create();
        $categoriaB = CategoriaDocumento::factory()->create();
        TipoDocumento::factory()->for($categoriaA, 'categoria')->create(['nome' => 'Da Categoria A']);
        TipoDocumento::factory()->for($categoriaB, 'categoria')->create(['nome' => 'Da Categoria B']);

        $resultado = app(ListarTiposDocumentoAction::class)->handle(15, CampoOrdenacaoTiposDocumento::Nome, DirecaoOrdenacao::Asc, $categoriaA->id);

        expect($resultado->pluck('nome')->all())->toBe(['Da Categoria A']);
    });

    it('devolve lista vazia quando id_categoria não tem correspondência', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        TipoDocumento::factory()->for($categoria, 'categoria')->create();

        $resultado = app(ListarTiposDocumentoAction::class)->handle(15, CampoOrdenacaoTiposDocumento::Nome, DirecaoOrdenacao::Asc, (string) Str::uuid7());

        expect($resultado->count())->toBe(0);
    });

    it('devolve categoria eager-loaded no resultado', function (): void {
        TipoDocumento::factory()->create();

        $resultado = app(ListarTiposDocumentoAction::class)->handle(15, CampoOrdenacaoTiposDocumento::Nome, DirecaoOrdenacao::Asc);

        expect($resultado->first()?->relationLoaded('categoria'))->toBeTrue();
    });
});

describe('sem permissão de leitura', function (): void {
    it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
        $this->actingAs(User::factory()->create()); // sem role — sem tipos-documento.ver

        expect(fn (): CursorPaginator => app(ListarTiposDocumentoAction::class)->handle(15, CampoOrdenacaoTiposDocumento::Nome, DirecaoOrdenacao::Asc))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    expect(fn (): CursorPaginator => app(ListarTiposDocumentoAction::class)->handle(15, CampoOrdenacaoTiposDocumento::Nome, DirecaoOrdenacao::Asc))
        ->toThrow(AuthorizationException::class);
});
