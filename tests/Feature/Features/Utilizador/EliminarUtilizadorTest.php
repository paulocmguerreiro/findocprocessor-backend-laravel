<?php

declare(strict_types=1);

use App\Models\EtapaDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

describe('autenticado com permissão', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('elimina utilizador sem referências por hard delete (204)', function (): void {
        $alvo = User::factory()->create();

        $this->deleteJson("/api/utilizadores/{$alvo->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $alvo->id]);
    });

    it('soft-delete (fallback) quando o utilizador é referenciado, revogando tokens', function (): void {
        $alvo = User::factory()->create();
        $alvo->createToken('api', ['api']);
        EtapaDocumento::factory()->create(['id_utilizador' => $alvo->id]);

        $this->deleteJson("/api/utilizadores/{$alvo->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $alvo->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $alvo->id]);
    });

    it('impede a auto-eliminação com 422', function (): void {
        $eu = auth()->user();

        $this->deleteJson("/api/utilizadores/{$eu->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $eu->id, 'deleted_at' => null]);
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    criarEAutenticarUtilizador();
    $alvo = User::factory()->create();

    $this->deleteJson("/api/utilizadores/{$alvo->id}")
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $alvo = User::factory()->create();

    $this->deleteJson("/api/utilizadores/{$alvo->id}")
        ->assertUnauthorized();
});
