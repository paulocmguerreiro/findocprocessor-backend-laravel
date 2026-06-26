<?php

declare(strict_types=1);

use App\Features\Role\Criar\CriarRoleAction;
use App\Features\Role\Criar\CriarRoleDto;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('cria role com permissões e retorna Role com permissions carregadas', function (): void {
        $dto = new CriarRoleDto(
            nome: 'editor',
            permissoes: ['entidades.ver', 'categorias-documento.ver'],
        );

        $role = (new CriarRoleAction)->handle($dto);

        expect($role->name)->toBe('editor')
            ->and($role->permissions)->toHaveCount(2)
            ->and($role->permissions->pluck('name')->sort()->values()->all())
            ->toBe(['categorias-documento.ver', 'entidades.ver']);

        $this->assertDatabaseHas('roles', ['name' => 'editor']);
    });
});

describe('sem permissão', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem roles.criar', function (): void {
        $dto = new CriarRoleDto(nome: 'editor', permissoes: []);

        expect(fn (): Role => (new CriarRoleAction)->handle($dto))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $dto = new CriarRoleDto(nome: 'editor', permissoes: []);

    expect(fn (): Role => (new CriarRoleAction)->handle($dto))
        ->toThrow(AuthorizationException::class);
});
