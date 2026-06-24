<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Criar\CriarCategoriaAction;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaDto;
use App\Models\CategoriaDocumento;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('cria categoria com dados válidos', function (): void {
        $dto = new CriarCategoriaDto(
            nome: 'Fornecedores',
            slug: 'fornecedores',
            tipoMovimento: TipoMovimento::Debito,
        );

        $resultado = app(CriarCategoriaAction::class)->handle($dto);

        expect($resultado->nome)->toBe('Fornecedores')
            ->and($resultado->slug)->toBe('fornecedores')
            ->and($resultado->tipo_movimento)->toBe(TipoMovimento::Debito);

        $this->assertDatabaseHas('categorias_documento', ['slug' => 'fornecedores']);
    });

    it('faz rollback quando ocorre excepção após insert', function (): void {
        CategoriaDocumento::created(function (): void {
            throw new RuntimeException('falha simulada após insert');
        });

        $dto = new CriarCategoriaDto(
            nome: 'Fornecedores',
            slug: 'fornecedores',
            tipoMovimento: TipoMovimento::Debito,
        );

        expect(fn (): CategoriaDocumento => app(CriarCategoriaAction::class)->handle($dto))
            ->toThrow(RuntimeException::class, 'falha simulada após insert');

        $this->assertDatabaseCount('categorias_documento', 0);
    });
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $dto = new CriarCategoriaDto(
            nome: 'Fornecedores',
            slug: 'fornecedores',
            tipoMovimento: TipoMovimento::Debito,
        );

        expect(fn (): CategoriaDocumento => app(CriarCategoriaAction::class)->handle($dto))
            ->toThrow(AuthorizationException::class);
    });
});
