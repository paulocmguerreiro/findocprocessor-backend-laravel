<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

describe('autenticado com permissão', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('restaura utilizador inactivo e devolve 200 com deleted_at null', function (): void {
        $alvo = User::factory()->inativo()->create();

        $this->patchJson("/api/utilizadores/{$alvo->id}/restaurar")
            ->assertOk()
            ->assertJsonPath('data.id', $alvo->id)
            ->assertJsonPath('data.deleted_at', null);

        $this->assertNotSoftDeleted('users', ['id' => $alvo->id]);
    });

    it('devolve 422 quando o utilizador não estava inactivo', function (): void {
        $alvo = User::factory()->create();

        $this->patchJson("/api/utilizadores/{$alvo->id}/restaurar")
            ->assertUnprocessable();
    });

    it('devolve 422 quando o utilizador está anonimizado', function (): void {
        $alvo = User::factory()->inativo()->create(['email' => 'anonimizado+7@removido.invalid']);

        $this->patchJson("/api/utilizadores/{$alvo->id}/restaurar")
            ->assertUnprocessable();

        $this->assertSoftDeleted('users', ['id' => $alvo->id]);
    });

    it('devolve 404 quando o utilizador não existe', function (): void {
        $this->patchJson('/api/utilizadores/999999/restaurar')
            ->assertNotFound();
    });

    it('utilizador restaurado volta a aparecer em GET /utilizadores', function (): void {
        $alvo = User::factory()->inativo()->create();

        $this->patchJson("/api/utilizadores/{$alvo->id}/restaurar")->assertOk();

        $this->getJson('/api/utilizadores')
            ->assertOk()
            ->assertJsonFragment(['id' => $alvo->id]);
    });
});

it('utilizador sem permissão recebe 403 e permanece inactivo', function (): void {
    $alvo = User::factory()->inativo()->create();
    criarEAutenticarUtilizador();

    $this->patchJson("/api/utilizadores/{$alvo->id}/restaurar")
        ->assertForbidden();

    $this->assertSoftDeleted('users', ['id' => $alvo->id]);
});

it('guest sem token recebe 401', function (): void {
    $alvo = User::factory()->inativo()->create();

    $this->patchJson("/api/utilizadores/{$alvo->id}/restaurar")
        ->assertUnauthorized();
});
