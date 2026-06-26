<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Ver\VerCategoriaAction;
use App\Models\CategoriaDocumento;
use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['categorias_documento'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('devolve o modelo quando recebe CategoriaDocumento directamente', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        $resultado = app(VerCategoriaAction::class)->handle($categoria);

        expect($resultado->id)->toBe($categoria->id);
    });

    it('resolve o modelo quando recebe string UUID', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        $resultado = app(VerCategoriaAction::class)->handle($categoria->id);

        expect($resultado->id)->toBe($categoria->id);
    });

    it('cacheia o registo após primeira chamada', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        app(VerCategoriaAction::class)->handle($categoria);

        $chave = app(CacheServico::class)->criarChave(
            TagCache::CategoriasDocumento,
            TagOperacao::Ver,
            ['id' => $categoria->id],
        );

        expect(Cache::tags(['categorias_documento'])->has($chave))->toBeTrue();
    });
});

describe('sem permissão de leitura', function (): void {
    it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
        $categoria = CategoriaDocumento::factory()->create();
        $this->actingAs(User::factory()->create()); // sem role — sem categorias-documento.ver

        expect(fn (): CategoriaDocumento => app(VerCategoriaAction::class)->handle($categoria))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $categoria = CategoriaDocumento::factory()->create();

    expect(fn (): CategoriaDocumento => app(VerCategoriaAction::class)->handle($categoria))
        ->toThrow(AuthorizationException::class);
});
