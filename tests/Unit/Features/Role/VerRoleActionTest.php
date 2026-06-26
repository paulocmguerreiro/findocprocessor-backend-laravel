<?php

declare(strict_types=1);

use App\Features\Role\Ver\VerRoleAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('devolve o role com as permissões carregadas', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $role->givePermissionTo('entidades.ver');

        $resultado = app(VerRoleAction::class)->handle($role);

        expect($resultado->is($role))->toBeTrue()
            ->and($resultado->relationLoaded('permissions'))->toBeTrue()
            ->and($resultado->permissions->pluck('name')->all())->toContain('entidades.ver');
    });
});

describe('sem permissão de leitura', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador())); // utilizador não tem roles.ver

    it('lança AuthorizationException quando utilizador não tem roles.ver', function (): void {
        $role = Role::findByName('admin', 'web');

        expect(fn (): Role => app(VerRoleAction::class)->handle($role))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();
    $role = Role::findByName('admin', 'web');

    expect(fn (): Role => app(VerRoleAction::class)->handle($role))
        ->toThrow(AuthorizationException::class);
});
