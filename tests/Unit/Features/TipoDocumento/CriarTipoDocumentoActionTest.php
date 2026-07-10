<?php

declare(strict_types=1);

use App\Features\TipoDocumento\Criar\CriarTipoDocumentoAction;
use App\Features\TipoDocumento\Criar\CriarTipoDocumentoDto;
use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['tipos_documento'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('cria tipo de documento com dados válidos', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        $dto = new CriarTipoDocumentoDto(
            nome: 'Fatura Fornecedor',
            descricao: 'Fatura emitida por um fornecedor',
            idCategoria: $categoria->id,
            posicaoEmpresaMae: PosicaoEmpresaMae::Cliente,
            esperaDataDocumento: true,
            esperaFornecedor: true,
            esperaCliente: false,
            esperaValor: true,
        );

        $resultado = app(CriarTipoDocumentoAction::class)->handle($dto);

        expect($resultado->nome)->toBe('Fatura Fornecedor')
            ->and($resultado->id_categoria)->toBe($categoria->id)
            ->and($resultado->relationLoaded('categoria'))->toBeTrue();

        $this->assertDatabaseHas('tipos_documento', ['nome' => 'Fatura Fornecedor']);
    });

    it('faz rollback quando ocorre excepção após insert', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        TipoDocumento::created(function (): void {
            throw new RuntimeException('falha simulada após insert');
        });

        $dto = new CriarTipoDocumentoDto(
            nome: 'Fatura Fornecedor',
            descricao: 'Fatura emitida por um fornecedor',
            idCategoria: $categoria->id,
            posicaoEmpresaMae: PosicaoEmpresaMae::Cliente,
            esperaDataDocumento: true,
            esperaFornecedor: true,
            esperaCliente: false,
            esperaValor: true,
        );

        expect(fn (): TipoDocumento => app(CriarTipoDocumentoAction::class)->handle($dto))
            ->toThrow(RuntimeException::class, 'falha simulada após insert');

        $this->assertDatabaseCount('tipos_documento', 0);
    });
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $categoria = CategoriaDocumento::factory()->create();

        $dto = new CriarTipoDocumentoDto(
            nome: 'Fatura Fornecedor',
            descricao: 'Fatura emitida por um fornecedor',
            idCategoria: $categoria->id,
            posicaoEmpresaMae: PosicaoEmpresaMae::Cliente,
            esperaDataDocumento: true,
            esperaFornecedor: true,
            esperaCliente: false,
            esperaValor: true,
        );

        expect(fn (): TipoDocumento => app(CriarTipoDocumentoAction::class)->handle($dto))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $categoria = CategoriaDocumento::factory()->create();

    $dto = new CriarTipoDocumentoDto(
        nome: 'Fatura Fornecedor',
        descricao: 'Fatura emitida por um fornecedor',
        idCategoria: $categoria->id,
        posicaoEmpresaMae: PosicaoEmpresaMae::Cliente,
        esperaDataDocumento: true,
        esperaFornecedor: true,
        esperaCliente: false,
        esperaValor: true,
    );

    expect(fn (): TipoDocumento => app(CriarTipoDocumentoAction::class)->handle($dto))
        ->toThrow(AuthorizationException::class);
});
