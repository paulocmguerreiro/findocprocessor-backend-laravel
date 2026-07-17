<?php

declare(strict_types=1);

use App\Features\Documento\Pesquisa\Listar\CampoOrdenacaoDocumentos;
use App\Features\Documento\Pesquisa\Listar\ListarDocumentosAction;
use App\Models\Documento;
use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['documentos'])->flush());

describe('autenticado', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('devolve lista vazia quando não há documentos', function (): void {
        $resultado = app(ListarDocumentosAction::class)
            ->handle(15, CampoOrdenacaoDocumentos::CriadoEm, DirecaoOrdenacao::Desc);

        expect($resultado->count())->toBe(0);
    });

    it('ordena por data do documento ascendente', function (): void {
        Documento::factory()->processado()->create(['data_documento' => '2026-03-01']);
        Documento::factory()->processado()->create(['data_documento' => '2026-01-01']);
        Documento::factory()->processado()->create(['data_documento' => '2026-02-01']);

        $resultado = app(ListarDocumentosAction::class)
            ->handle(15, CampoOrdenacaoDocumentos::DataDocumento, DirecaoOrdenacao::Asc);

        $datas = $resultado->getCollection()->map(fn (Documento $d): string => $d->data_documento->format('Y-m-d'))->all();

        expect($datas)->toBe(['2026-01-01', '2026-02-01', '2026-03-01']);
    });

    it('respeita o per_page na paginação por cursor', function (): void {
        Documento::factory()->count(5)->pendente()->create();

        $resultado = app(ListarDocumentosAction::class)
            ->handle(2, CampoOrdenacaoDocumentos::CriadoEm, DirecaoOrdenacao::Desc);

        expect($resultado->count())->toBe(2)
            ->and($resultado->nextCursor())->not->toBeNull();
    });

    it('filtra por estado quando indicado', function (): void {
        Documento::factory()->count(2)->pendente()->create();
        Documento::factory()->count(3)->processado()->create();

        $resultado = app(ListarDocumentosAction::class)
            ->handle(15, CampoOrdenacaoDocumentos::CriadoEm, DirecaoOrdenacao::Desc, EstadoDocumento::Pendente);

        expect($resultado->count())->toBe(2)
            ->and($resultado->getCollection()->every(fn (Documento $d): bool => $d->estado === EstadoDocumento::Pendente))->toBeTrue();
    });

    it('cacheia o resultado após a primeira chamada', function (): void {
        Documento::factory()->count(3)->processado()->create();

        app(ListarDocumentosAction::class)
            ->handle(15, CampoOrdenacaoDocumentos::CriadoEm, DirecaoOrdenacao::Desc);

        $chave = app(CacheServico::class)->criarChave(
            TagCache::Documentos,
            TagOperacao::Listar,
            [
                'campo' => CampoOrdenacaoDocumentos::CriadoEm->value,
                'cursor' => null,
                'direcao' => DirecaoOrdenacao::Desc->value,
                'estado' => null,
                'por_pagina' => 15,
            ],
        );

        expect(Cache::tags(['documentos'])->has($chave))->toBeTrue();
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    expect(fn (): CursorPaginator => app(ListarDocumentosAction::class)
        ->handle(15, CampoOrdenacaoDocumentos::CriadoEm, DirecaoOrdenacao::Desc))
        ->toThrow(AuthorizationException::class);
});

it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
    $this->actingAs(User::factory()->create()); // sem role — sem documentos.ver

    expect(fn (): CursorPaginator => app(ListarDocumentosAction::class)
        ->handle(15, CampoOrdenacaoDocumentos::CriadoEm, DirecaoOrdenacao::Desc))
        ->toThrow(AuthorizationException::class);
});
