<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum EstadoDocumento: string
{
    case Pendente = 'PENDENTE';
    case AguardaEnvio = 'AGUARDA_ENVIO';
    case Enviado = 'ENVIADO';
    case AguardaResposta = 'AGUARDA_RESPOSTA';
    case Processado = 'PROCESSADO';
    case Erro = 'ERRO';
    case Perigoso = 'PERIGOSO';
}
