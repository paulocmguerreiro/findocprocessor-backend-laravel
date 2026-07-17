<?php

declare(strict_types=1);

namespace App\Console\Commands\Extracao;

use App\Models\Documento;
use Illuminate\Console\Command;

/**
 * Base dos comandos `extracao:*` (RF-01/RF-03): reclama e processa documentos de
 * uma etapa do pipeline chamando repetidamente a Action orquestradora até não
 * haver candidato ou se atingir o limite do lote. As subclasses só ligam o comando
 * à Action (`processarProximo()`) e definem o tamanho do lote (`loteMaximo()`: 1 nas
 * etapas pesadas — Tesseract/IA local, M1 8GB; em lote nas restantes). Fino como
 * um Controller — sem lógica de negócio.
 */
abstract class EtapaExtracaoCommand extends Command
{
    protected const int LOTE_PADRAO = 25;

    public function handle(): int
    {
        $processados = 0;

        while ($processados < $this->loteMaximo() && $this->processarProximo() instanceof Documento) {
            $processados++;
        }

        $this->info(sprintf('%s: %d documento(s) processado(s).', $this->getName() ?? static::class, $processados));

        return self::SUCCESS;
    }

    /** Processa o próximo documento da etapa; `null` quando não há candidato. */
    abstract protected function processarProximo(): ?Documento;

    /** Máximo de documentos por ciclo: 1 nas etapas pesadas, em lote nas restantes. */
    abstract protected function loteMaximo(): int;
}
