<?php

declare(strict_types=1);

use App\Features\Documento\Operacoes\Transicao\RegraNomearProcessado;
use Illuminate\Support\Carbon;

it('gera o nome canónico com data, slugs e extensão', function (): void {
    $nome = (new RegraNomearProcessado)->handle(
        Carbon::parse('2026-06-25'),
        'Fornecedor Lda',
        null,
        'Despesas Gerais',
        'fatura.pdf',
        Carbon::parse('2026-06-30'),
    );

    expect($nome)->toBe('2026-06-25-fornecedor-lda-despesas-gerais.pdf');
});

it('faz slug de nomes com acentos e símbolos', function (): void {
    $nome = (new RegraNomearProcessado)->handle(
        Carbon::parse('2026-01-03'),
        'Electricidade & Água, SA',
        null,
        'Comunicações',
        'conta.PDF',
        Carbon::parse('2026-01-10'),
    );

    expect($nome)->toBe('2026-01-03-electricidade-agua-sa-comunicacoes.pdf');
});

it('preserva a extensão em minúsculas', function (): void {
    $nome = (new RegraNomearProcessado)->handle(
        Carbon::parse('2026-06-25'),
        'X',
        null,
        'Y',
        'RECIBO.JPEG',
        Carbon::parse('2026-06-30'),
    );

    expect($nome)->toBe('2026-06-25-x-y.jpeg');
});

it('omite o ponto quando o ficheiro original não tem extensão', function (): void {
    $nome = (new RegraNomearProcessado)->handle(
        Carbon::parse('2026-06-25'),
        'Fornecedor',
        null,
        'Categoria',
        'documento-sem-extensao',
        Carbon::parse('2026-06-30'),
    );

    expect($nome)->toBe('2026-06-25-fornecedor-categoria');
});

it('usa o created_at quando a data do documento é nula', function (): void {
    $nome = (new RegraNomearProcessado)->handle(
        null,
        'Fornecedor Lda',
        null,
        'Despesas',
        'fatura.pdf',
        Carbon::parse('2026-07-17 09:30:00'),
    );

    expect($nome)->toBe('2026-07-17-fornecedor-lda-despesas.pdf');
});

it('usa o nome extraído quando o fornecedor reconciliado é nulo', function (): void {
    $nome = (new RegraNomearProcessado)->handle(
        Carbon::parse('2026-06-25'),
        null,
        'Banco Comercial XYZ',
        'Extratos',
        'extrato.pdf',
        Carbon::parse('2026-06-30'),
    );

    expect($nome)->toBe('2026-06-25-banco-comercial-xyz-extratos.pdf');
});

it('usa o nome extraído quando o fornecedor reconciliado é vazio', function (): void {
    $nome = (new RegraNomearProcessado)->handle(
        Carbon::parse('2026-06-25'),
        '   ',
        'Banco XYZ',
        'Extratos',
        'extrato.pdf',
        Carbon::parse('2026-06-30'),
    );

    expect($nome)->toBe('2026-06-25-banco-xyz-extratos.pdf');
});
