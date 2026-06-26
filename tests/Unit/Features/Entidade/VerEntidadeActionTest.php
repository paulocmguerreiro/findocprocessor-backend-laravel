<?php

declare(strict_types=1);

use App\Features\Entidade\Ver\VerEntidadeAction;
use App\Models\Entidade;
use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['entidades'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('devolve o modelo quando recebe Entidade directamente', function (): void {
        $entidade = Entidade::factory()->create();

        $resultado = app(VerEntidadeAction::class)->handle($entidade);

        expect($resultado->id)->toBe($entidade->id);
    });

    it('resolve o modelo quando recebe string UUID', function (): void {
        $entidade = Entidade::factory()->create();

        $resultado = app(VerEntidadeAction::class)->handle($entidade->id);

        expect($resultado->id)->toBe($entidade->id);
    });

    it('cacheia o registo após primeira chamada', function (): void {
        $entidade = Entidade::factory()->create();

        app(VerEntidadeAction::class)->handle($entidade);

        $chave = app(CacheServico::class)->criarChave(
            TagCache::Entidades,
            TagOperacao::Ver,
            ['id' => $entidade->id],
        );

        expect(Cache::tags(['entidades'])->has($chave))->toBeTrue();
    });
});

describe('sem permissão de leitura', function (): void {
    it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
        $entidade = Entidade::factory()->create();
        $this->actingAs(User::factory()->create()); // sem role — sem entidades.ver

        expect(fn (): Entidade => app(VerEntidadeAction::class)->handle($entidade))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $entidade = Entidade::factory()->create();

    expect(fn (): Entidade => app(VerEntidadeAction::class)->handle($entidade))
        ->toThrow(AuthorizationException::class);
});
