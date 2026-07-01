<?php

declare(strict_types=1);

use App\Features\Utilizador\Anonimizar\AnonimizarUtilizadorAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('substitui dados pessoais, faz soft-delete e revoga tokens', function (): void {
        $alvo = User::factory()->create();
        $alvo->createToken('api', ['api']);

        app(AnonimizarUtilizadorAction::class)->handle($alvo);

        $this->assertSoftDeleted('users', ['id' => $alvo->id]);
        $this->assertDatabaseHas('users', [
            'id' => $alvo->id,
            'name' => 'Utilizador #'.$alvo->id,
            'email' => 'anonimizado+'.$alvo->id.'@removido.invalid',
            'email_verified_at' => null,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $alvo->id]);
    });

    it('regista evento rgpd.anonimizacao sem PII', function (): void {
        $alvo = User::factory()->create(['name' => 'Ana Silva', 'email' => 'ana@example.test']);

        app(AnonimizarUtilizadorAction::class)->handle($alvo);

        $registo = Activity::query()->where('event', 'rgpd.anonimizacao')->first();

        // O evento manual não carrega quaisquer propriedades (sem PII).
        expect($registo)->not->toBeNull()
            ->and($registo->properties->toArray())->toBe([]);

        // saveQuietly() suprime o evento 'updated' automático que registaria
        // old.name/old.email (PII) durante a substituição de dados.
        expect(Activity::query()->where('event', 'updated')->where('subject_id', $alvo->id)->exists())
            ->toBeFalse();
    });

    it('lança DomainException na auto-anonimização', function (): void {
        $eu = auth()->user();

        expect(fn () => app(AnonimizarUtilizadorAction::class)->handle($eu))
            ->toThrow(DomainException::class, 'Não é possível anonimizar o próprio utilizador.');
    });

    it('lança DomainException quando já está anonimizado', function (): void {
        $alvo = User::factory()->create(['email' => 'anonimizado+42@removido.invalid']);

        expect(fn () => app(AnonimizarUtilizadorAction::class)->handle($alvo))
            ->toThrow(DomainException::class, 'Utilizador já está anonimizado.');
    });

    it('faz rollback quando ocorre excepção durante a anonimização', function (): void {
        $alvo = User::factory()->create(['name' => 'Ana Silva', 'email' => 'ana@example.test']);

        User::deleting(function (): void {
            throw new RuntimeException('falha simulada durante anonimização');
        });

        expect(fn () => app(AnonimizarUtilizadorAction::class)->handle($alvo))
            ->toThrow(RuntimeException::class, 'falha simulada durante anonimização');

        $this->assertNotSoftDeleted('users', ['id' => $alvo->id]);
        $this->assertDatabaseHas('users', [
            'id' => $alvo->id,
            'name' => 'Ana Silva',
            'email' => 'ana@example.test',
        ]);
    });
});

it('sem permissão lança AuthorizationException', function (): void {
    $this->actingAs(criarUtilizador());
    $alvo = User::factory()->create();

    expect(fn () => app(AnonimizarUtilizadorAction::class)->handle($alvo))
        ->toThrow(AuthorizationException::class);
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    $alvo = User::factory()->create();

    expect(fn () => app(AnonimizarUtilizadorAction::class)->handle($alvo))
        ->toThrow(AuthorizationException::class);
});
