<?php

declare(strict_types=1);

namespace App\Shared\Cache;

enum TtlCache: int
{
    // Escala imediata: Ideal para memorizar registos individuais
    case Curta = 30;

    // Escala intermédia: Ideal para listagens e paginação
    case Media = 300;

    // Escala alargada: Ideal para tabelas gerais e dados transversais
    case Longa = 3600;

    // Escala máxima: Ideal para relatórios (controlada por mecanismos de invalidação)
    case Alargada = 86400;
}
