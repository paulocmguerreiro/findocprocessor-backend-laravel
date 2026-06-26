<?php

declare(strict_types=1);

namespace App\Features\Documento\Descarregar;

/**
 * Referência ao ficheiro de um Documento, devolvida por `DescarregarDocumentoAction`
 * para o Controller fazer o streaming. Mantém a Action agnóstica de HTTP.
 */
final readonly class FicheiroDocumentoDto
{
    public function __construct(
        public string $disco,
        public string $nomeStorage,
        public string $nomeOriginal,
    ) {}
}
