<?php

declare(strict_types=1);

use App\Features\Role\Eliminar\EliminarRoleAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('elimina role personalizado', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        (new EliminarRoleAction)->handle($role);

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);
    });

    it('lança DomainException ao tentar eliminar role admin', function (): void {
        $role = Role::findByName('admin', 'web');

        expect(fn () => (new EliminarRoleAction)->handle($role))
            ->toThrow(DomainException::class, 'Não é possível eliminar um role de sistema.');
    });

    it('lança DomainException ao tentar eliminar role utilizador', function (): void {
        $role = Role::findByName('utilizador', 'web');

        expect(fn () => (new EliminarRoleAction)->handle($role))
            ->toThrow(DomainException::class, 'Não é possível eliminar um role de sistema.');
    });
});

describe('sem permissão', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem roles.eliminar', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        expect(fn () => (new EliminarRoleAction)->handle($role))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    expect(fn () => (new EliminarRoleAction)->handle($role))
        ->toThrow(AuthorizationException::class);
});
