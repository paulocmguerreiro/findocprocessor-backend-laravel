<?php

declare(strict_types=1);

use App\Features\Documento\Processamento\RegistarEtapaExtracaoDto;
use App\Shared\Enums\ResultadoEtapa;

it('constrói com Sucesso sem motivo', function (): void {
    $dto = new RegistarEtapaExtracaoDto(ResultadoEtapa::Sucesso);

    expect($dto->resultado)->toBe(ResultadoEtapa::Sucesso)
        ->and($dto->motivo)->toBeNull();
});

it('constrói com EmCurso sem motivo', function (): void {
    $dto = new RegistarEtapaExtracaoDto(ResultadoEtapa::EmCurso);

    expect($dto->resultado)->toBe(ResultadoEtapa::EmCurso)
        ->and($dto->motivo)->toBeNull();
});

it('rejeita Falha sem motivo', function (): void {
    expect(fn (): RegistarEtapaExtracaoDto => new RegistarEtapaExtracaoDto(ResultadoEtapa::Falha))
        ->toThrow(InvalidArgumentException::class, 'motivo não pode ser vazio quando resultado é Falha.');
});

it('rejeita Falha com motivo em branco', function (): void {
    expect(fn (): RegistarEtapaExtracaoDto => new RegistarEtapaExtracaoDto(ResultadoEtapa::Falha, motivo: '   '))
        ->toThrow(InvalidArgumentException::class, 'motivo não pode ser vazio quando resultado é Falha.');
});

it('aceita Falha com motivo preenchido', function (): void {
    $dto = new RegistarEtapaExtracaoDto(ResultadoEtapa::Falha, motivo: 'timeout OCR');

    expect($dto->motivo)->toBe('timeout OCR');
});

it('define os defaults de reclamar/incrementarTentativas/textoExtraido/dadosJson', function (): void {
    $dto = new RegistarEtapaExtracaoDto(ResultadoEtapa::Sucesso);

    expect($dto->textoExtraido)->toBeNull()
        ->and($dto->dadosJson)->toBeNull()
        ->and($dto->reclamar)->toBeFalse()
        ->and($dto->incrementarTentativas)->toBeFalse();
});
