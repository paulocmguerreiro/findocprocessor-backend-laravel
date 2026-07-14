<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum ResultadoEtapa: string
{
    case Sucesso = 'SUCESSO';
    case Falha = 'FALHA';
    case EmCurso = 'EM_CURSO';
}
