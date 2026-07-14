<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum EtapaExtracao: string
{
    case Pendente = 'PENDENTE';
    case NecessitaOcr = 'NECESSITA_OCR';
    case TextoPronto = 'TEXTO_PRONTO';
    case NecessitaCloud = 'NECESSITA_CLOUD';
    case Concluido = 'CONCLUIDO';
    case Falhado = 'FALHADO';
}
