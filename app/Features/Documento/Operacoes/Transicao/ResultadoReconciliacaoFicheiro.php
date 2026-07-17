<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\Transicao;

/**
 * Resultado de `RegraReconciliarLocalizacaoFicheiro::handle()`: `coerente` quando
 * o ficheiro já está onde a BD espera; `encontrado` quando foi localizado noutro
 * disco conhecido (por hash); nenhum dos dois quando o ficheiro está perdido.
 */
final readonly class ResultadoReconciliacaoFicheiro
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public bool $coerente,
        public bool $encontrado,
        public ?string $disco = null,
        public ?string $nome = null,
    ) {
        if ($this->encontrado && ($this->disco === null || $this->nome === null)) {
            throw new \InvalidArgumentException('disco e nome são obrigatórios quando encontrado é true.');
        }
    }
}
