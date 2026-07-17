<?php

declare(strict_types=1);

use App\Features\Documento\Operacoes\Transicao\RegraNomearProcessado;
use Illuminate\Support\Carbon;

it('gera o nome canónico com data, slugs e extensão', function (): void {
    $nome = (new RegraNomearProcessado)->handle(
        Carbon::parse('2026-06-25'),
        'Fornecedor Lda',
        'Despesas Gerais',
        'fatura.pdf',
    );

    expect($nome)->toBe('2026-06-25-fornecedor-lda-despesas-gerais.pdf');
});

it('faz slug de nomes com acentos e símbolos', function (): void {
    $nome = (new RegraNomearProcessado)->handle(
        Carbon::parse('2026-01-03'),
        'Electricidade & Água, SA',
        'Comunicações',
        'conta.PDF',
    );

    expect($nome)->toBe('2026-01-03-electricidade-agua-sa-comunicacoes.pdf');
});

it('preserva a extensão em minúsculas', function (): void {
    $nome = (new RegraNomearProcessado)->handle(
        Carbon::parse('2026-06-25'),
        'X',
        'Y',
        'RECIBO.JPEG',
    );

    expect($nome)->toBe('2026-06-25-x-y.jpeg');
});

it('omite o ponto quando o ficheiro original não tem extensão', function (): void {
    $nome = (new RegraNomearProcessado)->handle(
        Carbon::parse('2026-06-25'),
        'Fornecedor',
        'Categoria',
        'documento-sem-extensao',
    );

    expect($nome)->toBe('2026-06-25-fornecedor-categoria');
});
