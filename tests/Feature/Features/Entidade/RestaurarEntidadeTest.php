<?php

declare(strict_types=1);

use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// Os seeds de roles deixam actividade persistente fora da transação do teste.
beforeEach(fn () => Activity::query()->delete());

describe('autenticado', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('restaura entidade inativa e devolve 200 com EntidadeResource', function (): void {
        $entidade = Entidade::factory()->inativa()->create();
        Activity::query()->delete();

        $this->patchJson("/api/entidades/{$entidade->id}/restaurar")
            ->assertOk()
            ->assertJsonPath('data.id', $entidade->id)
            ->assertJsonPath('data.deleted_at', null);

        $this->assertNotSoftDeleted('entidades', ['id' => $entidade->id]);

        expect(Activity::count())->toBe(1)
            ->and(Activity::query()->first()->event)->toBe('restored');
    });

    it('é idempotente para entidade activa e devolve 200', function (): void {
        $entidade = Entidade::factory()->create();

        $this->patchJson("/api/entidades/{$entidade->id}/restaurar")
            ->assertOk()
            ->assertJsonPath('data.id', $entidade->id);

        $this->assertNotSoftDeleted('entidades', ['id' => $entidade->id]);
    });

    it('devolve 404 quando UUID não existe', function (): void {
        $this->patchJson('/api/entidades/00000000-0000-0000-0000-000000000000/restaurar')
            ->assertNotFound();
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $entidade = Entidade::factory()->inativa()->create();
    criarEAutenticarUtilizador();

    $this->patchJson("/api/entidades/{$entidade->id}/restaurar")
        ->assertForbidden();

    $this->assertSoftDeleted('entidades', ['id' => $entidade->id]);
});

it('guest sem token recebe 401', function (): void {
    $entidade = Entidade::factory()->inativa()->create();

    $this->patchJson("/api/entidades/{$entidade->id}/restaurar")
        ->assertUnauthorized();
});
