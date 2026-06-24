<?php

declare(strict_types=1);

use App\Features\Entidade\Ver\VerEntidadeAction;
use App\Models\Entidade;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

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

    it('cacheia o registo — segunda chamada devolve resultado cacheado', function (): void {
        $entidade = Entidade::factory()->create(['nome' => 'Original']);

        app(VerEntidadeAction::class)->handle($entidade);

        // Alterar directamente na BD sem passar pela Action (bypass invalidação)
        $entidade->nome = 'Alterado';
        $entidade->saveQuietly();

        // Segunda chamada — deve devolver o valor cacheado ('Original')
        $resultado = app(VerEntidadeAction::class)->handle($entidade->id);

        expect($resultado->nome)->toBe('Original');
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
