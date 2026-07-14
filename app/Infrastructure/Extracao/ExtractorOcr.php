<?php

declare(strict_types=1);

namespace App\Infrastructure\Extracao;

use Illuminate\Support\Str;
use Imagick;
use ImagickException;
use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\TesseractOcrException;
use Throwable;

/**
 * Extrai o texto de um PDF digitalizado/imagem (sem camada de texto nativa)
 * rasterizando cada página via `imagick` a `config('extracao.ocr.dpi')` DPI
 * e correndo `thiagoalessio/tesseract_ocr` sobre cada imagem (RF-03). Nunca
 * calcula veredicto de threshold — `ultrapassaThreshold` fica sempre `null`
 * (RN-01, decisão da Issue IV).
 *
 * A memória do `Imagick` é libertada por página processada, não só no fim
 * do método (RF-04), e o directório de temporários
 * (`storage/app/temp/<uuid>-pagina-N.png`) é sempre limpo num bloco
 * `finally`, mesmo em falha a meio (RF-05).
 */
final readonly class ExtractorOcr
{
    /**
     * @throws FalhaExtracaoTextoException
     */
    public function extrair(string $caminhoAbsoluto): ResultadoExtracao
    {
        $idExecucao = Str::uuid()->toString();
        $documento = new Imagick;

        try {
            $textos = $this->rasterizarEReconhecer($caminhoAbsoluto, $documento, $idExecucao);

            return ResultadoExtracao::semVeredicto(implode("\n\n", $textos));
        } catch (Throwable $erro) {
            throw new FalhaExtracaoTextoException("Falha ao extrair texto por ocr de '{$caminhoAbsoluto}': {$erro->getMessage()}", $erro->getCode(), previous: $erro);
        } finally {
            $documento->clear();
            $documento->destroy();
            $this->limparTemporariosRemanescentes($idExecucao);
        }
    }

    /**
     * @return list<string>
     *
     * @throws ImagickException
     * @throws TesseractOcrException
     */
    private function rasterizarEReconhecer(string $caminhoAbsoluto, Imagick $documento, string $idExecucao): array
    {
        $dpi = config()->integer('extracao.ocr.dpi');
        /** @var list<string> $linguas */
        $linguas = config()->array('extracao.ocr.linguas');

        $documento->setResolution($dpi, $dpi);
        $documento->readImage($caminhoAbsoluto);

        $textos = [];

        for ($indice = 0; $indice < $documento->getNumberImages(); $indice++) {
            $documento->setIteratorIndex($indice);
            $pagina = $documento->getImage();
            $pagina->setImageFormat('png');

            $caminhoImagem = storage_path("app/temp/{$idExecucao}-pagina-{$indice}.png");
            $pagina->writeImage($caminhoImagem);

            try {
                $textos[] = new TesseractOCR($caminhoImagem)->lang(...$linguas)->run();
            } finally {
                $pagina->clear();
                $pagina->destroy();
                unlink($caminhoImagem);
            }
        }

        return $textos;
    }

    private function limparTemporariosRemanescentes(string $idExecucao): void
    {
        foreach (glob(storage_path("app/temp/{$idExecucao}-pagina-*.png")) ?: [] as $ficheiro) {
            unlink($ficheiro);
        }
    }
}
