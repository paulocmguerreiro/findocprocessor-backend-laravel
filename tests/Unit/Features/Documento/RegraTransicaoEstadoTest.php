<?php

declare(strict_types=1);

use App\Features\Documento\Operacoes\Transicao\RegraTransicaoEstado;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;

it('permite as transições do grafo', function (EstadoDocumento $de, EstadoDocumento $para): void {
    expect(fn () => (new RegraTransicaoEstado)->handle($de, $para))
        ->not->toThrow(TransicaoInvalidaException::class);
})->with([
    'pendente → análise malware' => [EstadoDocumento::Pendente, EstadoDocumento::AnaliseMalware],
    'análise malware → análise texto (limpo)' => [EstadoDocumento::AnaliseMalware, EstadoDocumento::AnaliseTexto],
    'análise malware → perigoso (infectado)' => [EstadoDocumento::AnaliseMalware, EstadoDocumento::Perigoso],
    'análise malware → erro (falha do scan)' => [EstadoDocumento::AnaliseMalware, EstadoDocumento::Erro],
    'análise texto → análise ia local' => [EstadoDocumento::AnaliseTexto, EstadoDocumento::AnaliseIaLocal],
    'análise texto → análise ocr' => [EstadoDocumento::AnaliseTexto, EstadoDocumento::AnaliseOcr],
    'análise texto → erro' => [EstadoDocumento::AnaliseTexto, EstadoDocumento::Erro],
    'análise ocr → análise ia local' => [EstadoDocumento::AnaliseOcr, EstadoDocumento::AnaliseIaLocal],
    'análise ocr → erro' => [EstadoDocumento::AnaliseOcr, EstadoDocumento::Erro],
    'análise ia local → processado' => [EstadoDocumento::AnaliseIaLocal, EstadoDocumento::Processado],
    'análise ia local → análise cloud' => [EstadoDocumento::AnaliseIaLocal, EstadoDocumento::AnaliseCloud],
    'análise ia local → perigoso (guardrail)' => [EstadoDocumento::AnaliseIaLocal, EstadoDocumento::Perigoso],
    'análise ia local → erro' => [EstadoDocumento::AnaliseIaLocal, EstadoDocumento::Erro],
    'análise cloud → processado' => [EstadoDocumento::AnaliseCloud, EstadoDocumento::Processado],
    'análise cloud → erro' => [EstadoDocumento::AnaliseCloud, EstadoDocumento::Erro],
    'análise cloud → perigoso (guardrail)' => [EstadoDocumento::AnaliseCloud, EstadoDocumento::Perigoso],
    'erro → pendente (reprocessar)' => [EstadoDocumento::Erro, EstadoDocumento::Pendente],
    'processado → processado (correcção)' => [EstadoDocumento::Processado, EstadoDocumento::Processado],
]);

it('rejeita transições fora do grafo com TransicaoInvalidaException', function (EstadoDocumento $de, EstadoDocumento $para): void {
    expect(fn () => (new RegraTransicaoEstado)->handle($de, $para))
        ->toThrow(TransicaoInvalidaException::class);
})->with([
    'perigoso → pendente (terminal)' => [EstadoDocumento::Perigoso, EstadoDocumento::Pendente],
    'pendente → processado (salto)' => [EstadoDocumento::Pendente, EstadoDocumento::Processado],
    'pendente → análise texto (salta o scan)' => [EstadoDocumento::Pendente, EstadoDocumento::AnaliseTexto],
    'análise malware → processado (salto)' => [EstadoDocumento::AnaliseMalware, EstadoDocumento::Processado],
    'análise texto → processado (salto)' => [EstadoDocumento::AnaliseTexto, EstadoDocumento::Processado],
    'análise ocr → análise cloud (salto)' => [EstadoDocumento::AnaliseOcr, EstadoDocumento::AnaliseCloud],
    'análise cloud → análise ia local (retrocesso)' => [EstadoDocumento::AnaliseCloud, EstadoDocumento::AnaliseIaLocal],
    'erro → processado' => [EstadoDocumento::Erro, EstadoDocumento::Processado],
    'processado → erro' => [EstadoDocumento::Processado, EstadoDocumento::Erro],
]);
