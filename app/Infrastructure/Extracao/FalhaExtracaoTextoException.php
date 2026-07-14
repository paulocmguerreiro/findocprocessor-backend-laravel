<?php

declare(strict_types=1);

namespace App\Infrastructure\Extracao;

use RuntimeException;

/**
 * Lançada por `ExtractorTextoNativo` ou `ExtractorOcr` quando ocorre uma
 * falha técnica (ficheiro corrompido/ilegível, falha do processo
 * `tesseract`/Ghostscript, falha de rasterização `imagick`). Nunca lançada
 * para "texto vazio" ou "abaixo do threshold" — esses são resultados
 * válidos (`ResultadoExtracao`), não uma falha.
 */
final class FalhaExtracaoTextoException extends RuntimeException {}
