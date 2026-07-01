<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

describe('autenticado com permissão', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('anonimiza o utilizador, faz soft-delete e devolve 204', function (): void {
        $alvo = User::factory()->create();
        $alvo->createToken('api', ['api']);

        $this->postJson("/api/utilizadores/{$alvo->id}/anonimizar")
            ->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $alvo->id]);
        $this->assertDatabaseHas('users', [
            'id' => $alvo->id,
            'name' => 'Utilizador #'.$alvo->id,
            'email' => 'anonimizado+'.$alvo->id.'@removido.invalid',
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $alvo->id]);
    });

    it('devolve 422 na auto-anonimização', function (): void {
        $eu = auth()->user();

        $this->postJson("/api/utilizadores/{$eu->id}/anonimizar")
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $eu->id, 'deleted_at' => null]);
    });

    it('devolve 422 quando o utilizador já está anonimizado', function (): void {
        $alvo = User::factory()->create(['email' => 'anonimizado+13@removido.invalid']);

        $this->postJson("/api/utilizadores/{$alvo->id}/anonimizar")
            ->assertUnprocessable();
    });

    it('devolve 404 quando o utilizador não existe', function (): void {
        $this->postJson('/api/utilizadores/999999/anonimizar')
            ->assertNotFound();
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $alvo = User::factory()->create();
    criarEAutenticarUtilizador();

    $this->postJson("/api/utilizadores/{$alvo->id}/anonimizar")
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $alvo->id, 'deleted_at' => null]);
});

it('guest sem token recebe 401', function (): void {
    $alvo = User::factory()->create();

    $this->postJson("/api/utilizadores/{$alvo->id}/anonimizar")
        ->assertUnauthorized();
});

it('o token do utilizador anonimizado fica inválido (401)', function (): void {
    $admin = criarAdmin();
    $adminToken = $admin->createToken('api', ['api'])->plainTextToken;

    $alvo = User::factory()->create();
    $alvoToken = $alvo->createToken('api', ['api'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->postJson("/api/utilizadores/{$alvo->id}/anonimizar")
        ->assertNoContent();

    // O guard sanctum memoriza o utilizador resolvido no pedido anterior;
    // limpar garante que o segundo pedido é reautenticado pelo token do alvo.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$alvoToken}")
        ->getJson('/api/utilizadores')
        ->assertUnauthorized();
});
