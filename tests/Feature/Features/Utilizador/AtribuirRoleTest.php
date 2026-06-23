<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('autenticado como admin', function (): void {
    it('atribui role a utilizador e devolve 204', function (): void {
        $admin = criarEAutenticarAdmin();
        $alvo = criarUtilizador();

        $this->putJson("/api/utilizadores/{$alvo->id}/role", [
            'role' => 'admin',
        ])->assertNoContent();

        expect($alvo->fresh()->hasRole('admin'))->toBeTrue()
            ->and($alvo->fresh()->hasRole('utilizador'))->toBeFalse();
    });

    it('devolve 422 quando role não existe', function (): void {
        criarEAutenticarAdmin();
        $alvo = criarUtilizador();

        $this->putJson("/api/utilizadores/{$alvo->id}/role", [
            'role' => 'role-inexistente',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    });

    it('devolve 422 quando campo role está em falta', function (): void {
        criarEAutenticarAdmin();
        $alvo = criarUtilizador();

        $this->putJson("/api/utilizadores/{$alvo->id}/role", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    });

    it('devolve 422 quando admin tenta alterar o próprio role', function (): void {
        $admin = criarEAutenticarAdmin();

        $this->putJson("/api/utilizadores/{$admin->id}/role", [
            'role' => 'utilizador',
        ])->assertUnprocessable();

        expect($admin->fresh()->hasRole('admin'))->toBeTrue();
    });

    it('devolve 404 quando utilizador não existe', function (): void {
        criarEAutenticarAdmin();

        $this->putJson('/api/utilizadores/00000000-0000-0000-0000-000000000000/role', [
            'role' => 'utilizador',
        ])->assertNotFound();
    });
});

it('utilizador sem utilizadores.atribuir-role recebe 403', function (): void {
    criarEAutenticarUtilizador();
    $alvo = User::factory()->create();

    $this->putJson("/api/utilizadores/{$alvo->id}/role", ['role' => 'utilizador'])
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $alvo = User::factory()->create();

    $this->putJson("/api/utilizadores/{$alvo->id}/role", ['role' => 'utilizador'])
        ->assertUnauthorized();
});
