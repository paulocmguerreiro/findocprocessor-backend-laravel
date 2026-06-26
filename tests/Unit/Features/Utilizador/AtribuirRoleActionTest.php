<?php

declare(strict_types=1);

use App\Features\Utilizador\AtribuirRole\AtribuirRoleAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('syncRoles substitui role anterior', function (): void {
        $alvo = criarAdmin();

        (new AtribuirRoleAction)->handle($alvo, 'utilizador');

        expect($alvo->fresh()->hasRole('utilizador'))->toBeTrue()
            ->and($alvo->fresh()->hasRole('admin'))->toBeFalse();
    });

    it('retorna utilizador com roles carregadas', function (): void {
        $alvo = User::factory()->create();

        $resultado = (new AtribuirRoleAction)->handle($alvo, 'utilizador');

        expect($resultado->relationLoaded('roles'))->toBeTrue()
            ->and($resultado->hasRole('utilizador'))->toBeTrue();
    });

    it('lança DomainException quando admin tenta alterar o próprio role', function (): void {
        $admin = criarAdmin();
        $this->actingAs($admin);

        expect(fn (): User => (new AtribuirRoleAction)->handle($admin, 'utilizador'))
            ->toThrow(DomainException::class, 'Não é possível alterar o próprio role.');

        expect($admin->fresh()->hasRole('admin'))->toBeTrue();
    });
});

describe('sem permissão', function (): void {
    it('lança AuthorizationException quando utilizador não tem utilizadores.atribuir-role', function (): void {
        $utilizador = criarUtilizador();
        $this->actingAs($utilizador);
        $alvo = User::factory()->create();

        expect(fn (): User => (new AtribuirRoleAction)->handle($alvo, 'utilizador'))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $alvo = User::factory()->create();

    expect(fn (): User => (new AtribuirRoleAction)->handle($alvo, 'utilizador'))
        ->toThrow(AuthorizationException::class);
});
