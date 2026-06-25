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

    it('elimina entidade e devolve 204', function (): void {
        $entidade = Entidade::factory()->create();
        Activity::query()->delete();

        $this->deleteJson("/api/entidades/{$entidade->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('entidades', ['id' => $entidade->id]);

        expect(Activity::count())->toBe(1)
            ->and(Activity::query()->first()->event)->toBe('deleted');
    });

    it('devolve 404 quando UUID não existe', function (): void {
        $this->deleteJson('/api/entidades/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $entidade = Entidade::factory()->create();
    criarEAutenticarUtilizador();
    Activity::query()->delete();

    $this->deleteJson("/api/entidades/{$entidade->id}")
        ->assertForbidden();

    expect(Activity::count())->toBe(0);
});

it('guest sem token recebe 401', function (): void {
    $entidade = Entidade::factory()->create();

    $this->deleteJson("/api/entidades/{$entidade->id}")
        ->assertUnauthorized();
});
