<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// Os seeds de roles deixam actividade persistente fora da transação do teste.
beforeEach(fn () => Activity::query()->delete());

describe('autenticado como admin', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('restaura categoria inactiva e devolve 200 com resource', function (): void {
        $categoria = CategoriaDocumento::factory()->inativa()->create();
        Activity::query()->delete();

        $this->patchJson("/api/categorias-documento/{$categoria->id}/restaurar")
            ->assertOk()
            ->assertJsonPath('data.id', $categoria->id)
            ->assertJsonPath('data.deleted_at', null);

        $this->assertDatabaseHas('categorias_documento', ['id' => $categoria->id, 'deleted_at' => null]);

        expect(Activity::count())->toBe(1)
            ->and(Activity::query()->first()->event)->toBe('restored');
    });

    it('devolve 404 quando categoria não existe (UUID inexistente)', function (): void {
        $this->patchJson('/api/categorias-documento/00000000-0000-0000-0000-000000000000/restaurar')
            ->assertNotFound();
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $categoria = CategoriaDocumento::factory()->inativa()->create();
    criarEAutenticarUtilizador();

    $this->patchJson("/api/categorias-documento/{$categoria->id}/restaurar")
        ->assertForbidden();

    $this->assertSoftDeleted('categorias_documento', ['id' => $categoria->id]);
});

it('guest sem token recebe 401', function (): void {
    $categoria = CategoriaDocumento::factory()->inativa()->create();

    $this->patchJson("/api/categorias-documento/{$categoria->id}/restaurar")
        ->assertUnauthorized();
});
