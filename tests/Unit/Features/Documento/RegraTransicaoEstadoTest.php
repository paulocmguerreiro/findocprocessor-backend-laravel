<?php

declare(strict_types=1);

use App\Features\Documento\Transicao\RegraTransicaoEstado;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;

it('permite as transições do grafo', function (EstadoDocumento $de, EstadoDocumento $para): void {
    expect(fn () => (new RegraTransicaoEstado)->handle($de, $para))
        ->not->toThrow(TransicaoInvalidaException::class);
})->with([
    'pendente → aguarda envio' => [EstadoDocumento::Pendente, EstadoDocumento::AguardaEnvio],
    'pendente → perigoso (pré-scan)' => [EstadoDocumento::Pendente, EstadoDocumento::Perigoso],
    'pendente → erro (falha do scan de malware)' => [EstadoDocumento::Pendente, EstadoDocumento::Erro],
    'aguarda envio → enviado' => [EstadoDocumento::AguardaEnvio, EstadoDocumento::Enviado],
    'enviado → aguarda resposta' => [EstadoDocumento::Enviado, EstadoDocumento::AguardaResposta],
    'aguarda resposta → processado' => [EstadoDocumento::AguardaResposta, EstadoDocumento::Processado],
    'aguarda resposta → erro' => [EstadoDocumento::AguardaResposta, EstadoDocumento::Erro],
    'aguarda resposta → perigoso (guardrail)' => [EstadoDocumento::AguardaResposta, EstadoDocumento::Perigoso],
    'erro → aguarda envio (reprocessar)' => [EstadoDocumento::Erro, EstadoDocumento::AguardaEnvio],
    'processado → processado (correcção)' => [EstadoDocumento::Processado, EstadoDocumento::Processado],
]);

it('rejeita transições fora do grafo com TransicaoInvalidaException', function (EstadoDocumento $de, EstadoDocumento $para): void {
    expect(fn () => (new RegraTransicaoEstado)->handle($de, $para))
        ->toThrow(TransicaoInvalidaException::class);
})->with([
    'processado → enviado' => [EstadoDocumento::Processado, EstadoDocumento::Enviado],
    'pendente → processado (salto)' => [EstadoDocumento::Pendente, EstadoDocumento::Processado],
    'aguarda envio → processado (salto)' => [EstadoDocumento::AguardaEnvio, EstadoDocumento::Processado],
    'enviado → erro' => [EstadoDocumento::Enviado, EstadoDocumento::Erro],
    'erro → processado' => [EstadoDocumento::Erro, EstadoDocumento::Processado],
    'perigoso → pendente (terminal)' => [EstadoDocumento::Perigoso, EstadoDocumento::Pendente],
    'processado → erro' => [EstadoDocumento::Processado, EstadoDocumento::Erro],
]);
