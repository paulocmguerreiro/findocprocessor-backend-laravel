<?php

declare(strict_types=1);

use App\Infrastructure\AI\ResultadoExtracaoIA;
use App\Infrastructure\AI\VeredictoExtracaoIA;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('produz um resultado completo com os dados normalizados', function (): void {
    $tipoDocumento = TipoDocumento::factory()->create();
    $dataDocumento = new DateTimeImmutable('2026-07-15');

    $resultado = ResultadoExtracaoIA::completo(
        tipoDocumento: $tipoDocumento,
        idCategoria: $tipoDocumento->id_categoria,
        dataDocumento: $dataDocumento,
        nifFornecedor: '123456789',
        nomeFornecedor: 'Fornecedor Lda',
        nifCliente: '987654321',
        nomeCliente: 'Cliente Lda',
        valor: 123.45,
    );

    expect($resultado->veredicto)->toBe(VeredictoExtracaoIA::Completo)
        ->and($resultado->ehCompleto())->toBeTrue()
        ->and($resultado->tipoDocumento)->toBe($tipoDocumento)
        ->and($resultado->idCategoria)->toBe($tipoDocumento->id_categoria)
        ->and($resultado->dataDocumento)->toBe($dataDocumento)
        ->and($resultado->nifFornecedor)->toBe('123456789')
        ->and($resultado->nomeFornecedor)->toBe('Fornecedor Lda')
        ->and($resultado->nifCliente)->toBe('987654321')
        ->and($resultado->nomeCliente)->toBe('Cliente Lda')
        ->and($resultado->valor)->toBe(123.45)
        ->and($resultado->motivo)->toBeNull()
        ->and($resultado->motivosFalta)->toBe([]);
});

it('produz um resultado completo com campos nullable quando não esperados', function (): void {
    $tipoDocumento = TipoDocumento::factory()->create();

    $resultado = ResultadoExtracaoIA::completo(
        tipoDocumento: $tipoDocumento,
        idCategoria: $tipoDocumento->id_categoria,
        dataDocumento: null,
        nifFornecedor: null,
        nomeFornecedor: null,
        nifCliente: null,
        nomeCliente: null,
        valor: null,
    );

    expect($resultado->ehCompleto())->toBeTrue()
        ->and($resultado->dataDocumento)->toBeNull()
        ->and($resultado->nifFornecedor)->toBeNull()
        ->and($resultado->valor)->toBeNull();
});

it('produz um resultado desconhecido', function (): void {
    $resultado = ResultadoExtracaoIA::desconhecido();

    expect($resultado->veredicto)->toBe(VeredictoExtracaoIA::Desconhecido)
        ->and($resultado->ehDesconhecido())->toBeTrue()
        ->and($resultado->ehCompleto())->toBeFalse()
        ->and($resultado->tipoDocumento)->toBeNull();
});

it('produz um resultado perigoso com o motivo', function (): void {
    $resultado = ResultadoExtracaoIA::perigoso('tipo_documento = "perigoso"');

    expect($resultado->veredicto)->toBe(VeredictoExtracaoIA::Perigoso)
        ->and($resultado->ehPerigoso())->toBeTrue()
        ->and($resultado->motivo)->toBe('tipo_documento = "perigoso"');
});

it('rejeita perigoso sem motivo', function (): void {
    expect(fn (): ResultadoExtracaoIA => ResultadoExtracaoIA::perigoso(''))
        ->toThrow(InvalidArgumentException::class, 'motivo é obrigatório quando o veredicto é perigoso ou falha técnica.');
});

it('produz um resultado incompleto com a lista de motivos', function (): void {
    $resultado = ResultadoExtracaoIA::incompleto(['valor em falta', 'nif do fornecedor inválido']);

    expect($resultado->veredicto)->toBe(VeredictoExtracaoIA::Incompleto)
        ->and($resultado->ehIncompleto())->toBeTrue()
        ->and($resultado->motivosFalta)->toBe(['valor em falta', 'nif do fornecedor inválido']);
});

it('rejeita incompleto sem motivos de falta', function (): void {
    expect(fn (): ResultadoExtracaoIA => ResultadoExtracaoIA::incompleto([]))
        ->toThrow(InvalidArgumentException::class, 'motivosFalta não pode ser vazio quando o veredicto é incompleto.');
});

it('produz um resultado de falha técnica com o motivo', function (): void {
    $resultado = ResultadoExtracaoIA::falhaTecnica('timeout ao contactar o provider');

    expect($resultado->veredicto)->toBe(VeredictoExtracaoIA::FalhaTecnica)
        ->and($resultado->estaEmFalhaTecnica())->toBeTrue()
        ->and($resultado->motivo)->toBe('timeout ao contactar o provider');
});

it('rejeita falha técnica sem motivo', function (): void {
    expect(fn (): ResultadoExtracaoIA => ResultadoExtracaoIA::falhaTecnica(''))
        ->toThrow(InvalidArgumentException::class, 'motivo é obrigatório quando o veredicto é perigoso ou falha técnica.');
});
