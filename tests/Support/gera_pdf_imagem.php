<?php

declare(strict_types=1);
use Illuminate\Support\Str;

/**
 * Gera, em runtime, um PDF multi-página sem camada de texto (imagem pura) —
 * desenha `$textosPorPagina[$i]` como texto raster na página `$i`, via
 * PostScript + Ghostscript (`gs`) para rasterizar a texto em pixels (sem
 * dependência de resolução de fontes do `imagick`, indisponível em alguns
 * ambientes) e `imagick` para combinar as imagens num único PDF. Usado como
 * fixture do `ExtractorOcrTest` para não commitar um binário frágil/
 * dependente do motor de renderização; o texto desenhado é conhecido e
 * permite asserção por substring/palavra-chave (RNF-05/CA-07).
 *
 * @param  list<string>  $textosPorPagina
 */
function gera_pdf_imagem(string $caminhoDestino, array $textosPorPagina): void
{
    $idTemp = Str::uuid()->toString();
    $caminhoPs = storage_path("app/temp/{$idTemp}.ps");
    $prefixoPng = storage_path("app/temp/{$idTemp}-pagina");

    $ps = "%!PS\n";
    foreach ($textosPorPagina as $texto) {
        $textoEscapado = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
        $ps .= "/Helvetica findfont 36 scalefont setfont\n";
        $ps .= "50 150 moveto\n";
        $ps .= "({$textoEscapado}) show\n";
        $ps .= "showpage\n";
    }

    file_put_contents($caminhoPs, $ps);

    try {
        $comando = sprintf(
            'gs -q -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 -o %s-%%d.png %s',
            escapeshellarg($prefixoPng),
            escapeshellarg($caminhoPs),
        );
        exec($comando, result_code: $codigo);

        if ($codigo !== 0) {
            throw new RuntimeException("Falha ao rasterizar fixture PS→PNG via ghostscript (código {$codigo}).");
        }

        $documento = new Imagick;

        foreach (range(1, count($textosPorPagina)) as $numeroPagina) {
            $caminhoPng = "{$prefixoPng}-{$numeroPagina}.png";
            $pagina = new Imagick($caminhoPng);
            $documento->addImage($pagina);
            $pagina->clear();
            $pagina->destroy();
            unlink($caminhoPng);
        }

        $documento->setImageFormat('pdf');
        $documento->writeImages($caminhoDestino, true);
        $documento->clear();
        $documento->destroy();
    } finally {
        if (file_exists($caminhoPs)) {
            unlink($caminhoPs);
        }
    }
}
