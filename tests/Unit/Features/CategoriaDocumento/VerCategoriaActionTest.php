<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Ver\VerCategoriaAction;
use App\Models\CategoriaDocumento;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

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

    it('cacheia o registo — segunda chamada devolve resultado cacheado', function (): void {
        $categoria = CategoriaDocumento::factory()->create(['nome' => 'Original']);

        app(VerCategoriaAction::class)->handle($categoria);

        // Alterar directamente na BD sem passar pela Action (bypass invalidação)
        $categoria->nome = 'Alterado';
        $categoria->saveQuietly();

        // Segunda chamada — deve devolver o valor cacheado ('Original')
        $resultado = app(VerCategoriaAction::class)->handle($categoria->id);

        expect($resultado->nome)->toBe('Original');
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
