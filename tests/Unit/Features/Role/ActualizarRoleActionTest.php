<?php

declare(strict_types=1);

use App\Features\Role\Actualizar\ActualizarRoleAction;
use App\Features\Role\Actualizar\ActualizarRoleDto;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('sync de permissões substitui todas as anteriores', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $role->givePermissionTo(['entidades.ver', 'entidades.criar']);

        $dto = new ActualizarRoleDto(nome: null, permissoes: ['categorias-documento.ver']);

        $resultado = (new ActualizarRoleAction)->handle($role, $dto);

        expect($resultado->permissions)->toHaveCount(1)
            ->and($resultado->permissions->first()->name)->toBe('categorias-documento.ver');
    });

    it('actualiza nome quando $dados->nome não é null', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $dto = new ActualizarRoleDto(nome: 'revisor', permissoes: ['entidades.ver']);

        $resultado = (new ActualizarRoleAction)->handle($role, $dto);

        expect($resultado->name)->toBe('revisor');
        $this->assertDatabaseHas('roles', ['name' => 'revisor']);
    });

    it('não altera nome quando $dados->nome é null', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $dto = new ActualizarRoleDto(nome: null, permissoes: ['entidades.ver']);

        $resultado = (new ActualizarRoleAction)->handle($role, $dto);

        expect($resultado->name)->toBe('editor');
    });
});

describe('sem permissão', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem roles.actualizar', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $dto = new ActualizarRoleDto(nome: null, permissoes: []);

        expect(fn (): Role => (new ActualizarRoleAction)->handle($role, $dto))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $dto = new ActualizarRoleDto(nome: null, permissoes: []);

    expect(fn (): Role => (new ActualizarRoleAction)->handle($role, $dto))
        ->toThrow(AuthorizationException::class);
});
