<?php

declare(strict_types=1);

use App\Features\Utilizador\Restaurar\RestaurarUtilizadorAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('restaura utilizador inactivo quando recebe User directamente', function (): void {
        $alvo = User::factory()->inativo()->create();

        $restaurado = app(RestaurarUtilizadorAction::class)->handle($alvo);

        expect($restaurado)->toBeInstanceOf(User::class);
        $this->assertNotSoftDeleted('users', ['id' => $alvo->id]);
    });

    it('restaura utilizador inactivo quando recebe int PK', function (): void {
        $alvo = User::factory()->inativo()->create();

        app(RestaurarUtilizadorAction::class)->handle($alvo->id);

        $this->assertNotSoftDeleted('users', ['id' => $alvo->id]);
    });

    it('lança DomainException quando o utilizador não estava inactivo', function (): void {
        $alvo = User::factory()->create();

        expect(fn () => app(RestaurarUtilizadorAction::class)->handle($alvo))
            ->toThrow(DomainException::class, 'Utilizador não está inactivo.');
    });

    it('lança DomainException quando o utilizador está anonimizado', function (): void {
        $alvo = User::factory()->inativo()->create([
            'email' => 'anonimizado+999@removido.invalid',
        ]);

        expect(fn () => app(RestaurarUtilizadorAction::class)->handle($alvo))
            ->toThrow(DomainException::class, 'Utilizador anonimizado não pode ser restaurado.');

        $this->assertSoftDeleted('users', ['id' => $alvo->id]);
    });

    it('faz rollback quando ocorre excepção durante o restauro', function (): void {
        $alvo = User::factory()->inativo()->create();

        User::restoring(function (): void {
            throw new RuntimeException('falha simulada durante restauro');
        });

        expect(fn () => app(RestaurarUtilizadorAction::class)->handle($alvo))
            ->toThrow(RuntimeException::class, 'falha simulada durante restauro');

        $this->assertSoftDeleted('users', ['id' => $alvo->id]);
    });
});

it('sem permissão lança AuthorizationException', function (): void {
    $this->actingAs(criarUtilizador());
    $alvo = User::factory()->inativo()->create();

    expect(fn () => app(RestaurarUtilizadorAction::class)->handle($alvo))
        ->toThrow(AuthorizationException::class);
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    $alvo = User::factory()->inativo()->create();

    expect(fn () => app(RestaurarUtilizadorAction::class)->handle($alvo))
        ->toThrow(AuthorizationException::class);
});
