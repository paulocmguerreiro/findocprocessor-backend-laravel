<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum EstadoDocumento: string
{
    case Pendente = 'PENDENTE';
    case AnaliseMalware = 'ANALISE_MALWARE';
    case AnaliseTexto = 'ANALISE_TEXTO';
    case AnaliseOcr = 'ANALISE_OCR';
    case AnaliseIaLocal = 'ANALISE_IA_LOCAL';
    case AnaliseCloud = 'ANALISE_CLOUD';
    case Processado = 'PROCESSADO';
    case Erro = 'ERRO';
    case Perigoso = 'PERIGOSO';
}
