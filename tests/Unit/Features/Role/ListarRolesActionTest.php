<?php

declare(strict_types=1);

use App\Features\Role\Listar\CampoOrdenacaoRoles;
use App\Features\Role\Listar\ListarRolesAction;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('devolve roles paginados com as permissões carregadas', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $role->givePermissionTo('entidades.ver');

        $resultado = app(ListarRolesAction::class)->handle(15, CampoOrdenacaoRoles::Nome, DirecaoOrdenacao::Asc);
        $editor = $resultado->getCollection()->firstWhere('name', 'editor');

        expect($resultado)->toBeInstanceOf(CursorPaginator::class)
            ->and($editor)->not->toBeNull()
            ->and($editor->relationLoaded('permissions'))->toBeTrue()
            ->and($editor->permissions->pluck('name')->all())->toContain('entidades.ver');
    });

    it('respeita o per_page na paginação por cursor', function (): void {
        // admin e utilizador já existem (seed); criar mais um garante mais do que o per_page.
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $resultado = app(ListarRolesAction::class)->handle(2, CampoOrdenacaoRoles::Nome, DirecaoOrdenacao::Asc);

        expect($resultado->count())->toBe(2)
            ->and($resultado->nextCursor())->not->toBeNull();
    });
});

describe('sem permissão de leitura', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador())); // utilizador não tem roles.ver

    it('lança AuthorizationException quando utilizador não tem roles.ver', function (): void {
        expect(fn (): CursorPaginator => app(ListarRolesAction::class)->handle(15, CampoOrdenacaoRoles::Nome, DirecaoOrdenacao::Asc))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    expect(fn (): CursorPaginator => app(ListarRolesAction::class)->handle(15, CampoOrdenacaoRoles::Nome, DirecaoOrdenacao::Asc))
        ->toThrow(AuthorizationException::class);
});
