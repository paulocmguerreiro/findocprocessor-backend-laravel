<?php

declare(strict_types=1);

use App\Features\Utilizador\Eliminar\EliminarUtilizadorAction;
use App\Models\EtapaDocumento;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('hard delete quando o utilizador não tem referências', function (): void {
        $alvo = User::factory()->create();

        app(EliminarUtilizadorAction::class)->handle($alvo);

        $this->assertDatabaseMissing('users', ['id' => $alvo->id]);
    });

    it('soft delete (fallback) quando referenciado, com deleted_at e tokens revogados', function (): void {
        $alvo = User::factory()->create();
        $alvo->createToken('api', ['api']);
        EtapaDocumento::factory()->create(['id_utilizador' => $alvo->id]);

        app(EliminarUtilizadorAction::class)->handle($alvo);

        $this->assertSoftDeleted('users', ['id' => $alvo->id]);
        expect($alvo->fresh()->deleted_at)->not->toBeNull();
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $alvo->id]);
    });

    it('impede a auto-eliminação com DomainException', function (): void {
        $eu = auth()->user();

        expect(fn () => app(EliminarUtilizadorAction::class)->handle($eu))
            ->toThrow(DomainException::class);
    });
});

it('sem permissão lança AuthorizationException', function (): void {
    $this->actingAs(criarUtilizador());
    $alvo = User::factory()->create();

    expect(fn () => app(EliminarUtilizadorAction::class)->handle($alvo))
        ->toThrow(AuthorizationException::class);
});
