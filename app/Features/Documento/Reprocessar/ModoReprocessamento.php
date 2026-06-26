<?php

declare(strict_types=1);

namespace App\Features\Documento\Reprocessar;

/**
 * Modo de reprocessamento de um Documento em estado `Erro`.
 * A semântica de fallback (catálogo de ferramentas, hierarquia OCR→modelos)
 * pertence à issue de extracção — aqui o valor é apenas aceite e propagado.
 */
enum ModoReprocessamento: string
{
    case Modelo = 'MODELO';
    case Ferramenta = 'FERRAMENTA';
}
