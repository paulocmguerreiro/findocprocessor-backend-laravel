<?php

declare(strict_types=1);

use App\Features\Utilizador\Listar\CampoOrdenacaoUtilizadores;
use App\Features\Utilizador\Listar\ListarUtilizadoresAction;
use App\Models\User;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Enums\FiltroEstadoRegisto;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

it('com permissão devolve um CursorPaginator de utilizadores', function (): void {
    $this->actingAs(criarAdmin());
    User::factory()->count(3)->create();

    $resultado = app(ListarUtilizadoresAction::class)->handle(
        15,
        CampoOrdenacaoUtilizadores::Nome,
        DirecaoOrdenacao::Asc,
        FiltroEstadoRegisto::SomenteAtivos,
    );

    expect($resultado)->toBeInstanceOf(CursorPaginator::class)
        ->and($resultado->count())->toBeGreaterThanOrEqual(4); // 3 + admin
});

it('com filtro SomenteInativos devolve apenas os inactivos', function (): void {
    $this->actingAs(criarAdmin());
    User::factory()->inativo()->count(2)->create();

    $resultado = app(ListarUtilizadoresAction::class)->handle(
        15,
        CampoOrdenacaoUtilizadores::Nome,
        DirecaoOrdenacao::Asc,
        FiltroEstadoRegisto::SomenteInativos,
    );

    expect($resultado->count())->toBe(2);
});

it('sem permissão lança AuthorizationException', function (): void {
    $this->actingAs(criarUtilizador());

    expect(fn (): CursorPaginator => app(ListarUtilizadoresAction::class)->handle(
        15,
        CampoOrdenacaoUtilizadores::Nome,
        DirecaoOrdenacao::Asc,
        FiltroEstadoRegisto::SomenteAtivos,
    ))->toThrow(AuthorizationException::class);
});
