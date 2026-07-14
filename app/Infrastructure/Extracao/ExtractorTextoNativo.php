<?php

declare(strict_types=1);

namespace App\Infrastructure\Extracao;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extrai o texto de um PDF digital (com camada de texto nativa) via
 * `smalot/pdfparser` e aplica a regra dos 50 caracteres
 * (RF-01/RF-02) — não decide nem grava transição de estado, só devolve o
 * veredicto no `ResultadoExtracao`.
 */
final readonly class ExtractorTextoNativo
{
    /**
     * @throws FalhaExtracaoTextoException
     */
    public function extrair(string $caminhoAbsoluto): ResultadoExtracao
    {
        try {
            $texto = (new Parser)->parseFile($caminhoAbsoluto)->getText();
        } catch (Throwable $erro) {
            throw new FalhaExtracaoTextoException("Falha ao extrair texto nativo de '{$caminhoAbsoluto}': {$erro->getMessage()}", $erro->getCode(), previous: $erro);
        }

        $ultrapassaThreshold = strlen(trim($texto)) > config()->integer('extracao.threshold_caracteres');

        return ResultadoExtracao::comVeredictoThreshold($texto, $ultrapassaThreshold);
    }
}
