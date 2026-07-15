<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

/**
 * Camada de IA a invocar por `ClienteExtracaoIAPrism::extrair()` — identifica
 * qual sub-árvore de `config('extracao.*')` (`local`/`cloud`) usar. Sempre
 * explícita no chamador: `ClienteExtracaoIAPrism` nunca decide sozinho qual
 * camada usar (essa decisão é do futuro orquestrador).
 */
enum CamadaIA: string
{
    case Local = 'local';
    case Cloud = 'cloud';
}
