<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

/**
 * Estado interno de `ResultadoExtracaoIA` — nunca exposto directamente, só
 * através dos getters de consulta (`ehCompleto()`, `ehDesconhecido()`,
 * `ehPerigoso()`, `ehIncompleto()`, `falhouTecnicamente()`).
 */
enum VeredictoExtracaoIA: string
{
    case Completo = 'COMPLETO';
    case Desconhecido = 'DESCONHECIDO';
    case Perigoso = 'PERIGOSO';
    case Incompleto = 'INCOMPLETO';
    case FalhaTecnica = 'FALHA_TECNICA';
}
