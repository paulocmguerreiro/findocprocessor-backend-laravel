<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Os seeds de roles deixam actividade persistente fora da transação do teste.
beforeEach(fn () => Activity::query()->delete());

describe('autenticado como admin', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('actualiza permissões e faz sync completo', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $role->givePermissionTo(['entidades.ver', 'entidades.criar']);

        $this->putJson("/api/roles/{$role->id}", [
            'permissoes' => ['categorias-documento.ver'],
        ])
            ->assertOk()
            ->assertJsonPath('data.nome', 'editor')
            ->assertJsonCount(1, 'data.permissoes');

        expect($role->fresh()->hasPermissionTo('entidades.ver'))->toBeFalse();
    });

    it('actualiza nome e permissões', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Activity::query()->delete();

        $this->putJson("/api/roles/{$role->id}", [
            'nome' => 'revisor',
            'permissoes' => ['entidades.ver'],
        ])
            ->assertOk()
            ->assertJsonPath('data.nome', 'revisor');

        $this->assertDatabaseHas('roles', ['name' => 'revisor']);

        expect(Activity::count())->toBe(1)
            ->and(Activity::query()->first()->event)->toBe('updated');
    });

    it('sem campo nome não altera o nome', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->putJson("/api/roles/{$role->id}", [
            'permissoes' => ['entidades.ver'],
        ])->assertOk()
            ->assertJsonPath('data.nome', 'editor');
    });

    it('devolve 422 quando permissão não existe', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->putJson("/api/roles/{$role->id}", [
            'permissoes' => ['permissao.inexistente'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['permissoes.0']);
    });

    it('devolve 404 quando role não existe', function (): void {
        $this->putJson('/api/roles/99999', [
            'permissoes' => ['entidades.ver'],
        ])->assertNotFound();
    });
});

it('utilizador sem roles.actualizar recebe 403', function (): void {
    criarEAutenticarUtilizador();
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    Activity::query()->delete();

    $this->putJson("/api/roles/{$role->id}", ['permissoes' => []])
        ->assertForbidden();

    expect(Activity::count())->toBe(0);
});

it('guest sem token recebe 401', function (): void {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->putJson("/api/roles/{$role->id}", ['permissoes' => []])
        ->assertUnauthorized();
});
