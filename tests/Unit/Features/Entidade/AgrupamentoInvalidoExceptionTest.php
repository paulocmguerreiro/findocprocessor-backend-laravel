<?php

declare(strict_types=1);

use App\Features\Entidade\Agrupar\AgrupamentoInvalidoException;

it('paraEntidadesIguais produz a mensagem esperada', function (): void {
    $excepcao = AgrupamentoInvalidoException::paraEntidadesIguais();

    expect($excepcao)
        ->toBeInstanceOf(DomainException::class)
        ->and($excepcao->getMessage())
        ->toBe('A entidade principal e a secundária têm de ser distintas.');
});

it('paraEmpresaAplicacao produz a mensagem esperada', function (): void {
    $excepcao = AgrupamentoInvalidoException::paraEmpresaAplicacao();

    expect($excepcao)
        ->toBeInstanceOf(DomainException::class)
        ->and($excepcao->getMessage())
        ->toBe('A entidade secundária não pode ser a empresa da aplicação.');
});

it('paraReferenciasNaoTratadas lista as colunas na mensagem', function (): void {
    $excepcao = AgrupamentoInvalidoException::paraReferenciasNaoTratadas(['faturas.id_entidade', 'contactos.id_entidade']);

    expect($excepcao)
        ->toBeInstanceOf(DomainException::class)
        ->and($excepcao->getMessage())
        ->toBe('Existem referências a entidades não tratadas pela fusão: faturas.id_entidade, contactos.id_entidade.');
});
