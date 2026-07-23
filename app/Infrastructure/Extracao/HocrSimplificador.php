<?php

declare(strict_types=1);

namespace App\Infrastructure\Extracao;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Reduz a saída hOCR do Tesseract a uma lista compacta de blocos com coordenadas:
 *
 *   <block bbox='x0 y0 x1 y1'>texto do bloco</block>
 *
 * Agrupa as PALAVRAS (`ocrx_word`) em **regiões 2D** por adjacência espacial
 * (union-find): une palavras horizontalmente na mesma linha (dentro de uma frase,
 * sem atravessar o *gutter* entre colunas) e verticalmente na mesma coluna. Assim,
 * o bloco de área do Tesseract — que lineariza um cabeçalho de duas colunas numa só
 * linha, cruzando o NIF do emissor (esquerda) com a data (direita) — é reconstruído
 * em regiões limpas: emissor (nome+morada+NIF) de um lado, metadados (Nº+Data) do
 * outro. As coordenadas `bbox` dão ao LLM a posição de cada região (só recebe texto).
 * Regiões ordenadas por Y e depois X (ordem de leitura visual).
 */
final readonly class HocrSimplificador
{
    /** Múltiplo da altura mediana da linha: intervalo horizontal acima do qual há fronteira de coluna. */
    private const float FACTOR_GUTTER = 2.5;

    /** Múltiplo da altura mediana da linha: distância vertical até à qual duas linhas são a mesma coluna. */
    private const float FACTOR_VERTICAL = 1.8;

    public function simplificar(string $hocr): string
    {
        if (trim($hocr) === '') {
            return '';
        }

        $palavras = $this->extrairPalavras($hocr);

        if ($palavras === []) {
            return '';
        }

        $altura = $this->calcularAlturaMediana($palavras);
        $regioes = $this->agruparEmRegioes($palavras, $altura * self::FACTOR_GUTTER, $altura * self::FACTOR_VERTICAL);

        usort($regioes, static fn (array $a, array $b): int => [$a['y0'], $a['x0']] <=> [$b['y0'], $b['x0']]);

        $linhas = array_map(
            static fn (array $r): string => sprintf("<block bbox='%d %d %d %d'>%s</block>", $r['x0'], $r['y0'], $r['x1'], $r['y1'], $r['texto']),
            $regioes,
        );

        return implode("\n", $linhas);
    }

    /**
     * @return list<array{x0: int, y0: int, x1: int, y1: int, texto: string}>
     */
    private function extrairPalavras(string $hocr): array
    {
        $dom = new DOMDocument;

        $usoAnterior = libxml_use_internal_errors(true);
        $dom->loadHTML($hocr, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($usoAnterior);

        $nos = new DOMXPath($dom)->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' ocrx_word ')]") ?: [];

        $palavras = [];

        foreach ($nos as $no) {
            $titulo = $no instanceof DOMElement ? $no->getAttribute('title') : '';

            if (preg_match('/bbox (\d+) (\d+) (\d+) (\d+)/', $titulo, $coordenadas) !== 1) {
                continue;
            }

            $texto = trim((string) preg_replace('/\s+/', ' ', $no->textContent));

            if ($texto === '') {
                continue;
            }

            $palavras[] = ['x0' => (int) $coordenadas[1], 'y0' => (int) $coordenadas[2], 'x1' => (int) $coordenadas[3], 'y1' => (int) $coordenadas[4], 'texto' => $texto];
        }

        return $palavras;
    }

    /**
     * Union-find sobre as palavras: junta as que são adjacentes (mesma frase ou mesma
     * coluna) e consolida cada componente numa região.
     *
     * @param  non-empty-list<array{x0: int, y0: int, x1: int, y1: int, texto: string}>  $palavras
     * @return list<array{x0: int, y0: int, x1: int, y1: int, texto: string}>
     */
    private function agruparEmRegioes(array $palavras, float $limiarGutter, float $limiarVertical): array
    {
        $total = count($palavras);
        $raiz = range(0, $total - 1);

        for ($i = 0; $i < $total; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                if ($this->eAdjacente($palavras[$i], $palavras[$j], $limiarGutter, $limiarVertical)) {
                    $raiz[$this->encontrar($raiz, $j)] = $this->encontrar($raiz, $i);
                }
            }
        }

        /** @var array<int, non-empty-list<array{x0: int, y0: int, x1: int, y1: int, texto: string}>> $grupos */
        $grupos = [];

        foreach ($palavras as $indice => $palavra) {
            $grupos[$this->encontrar($raiz, $indice)][] = $palavra;
        }

        return array_map($this->consolidar(...), array_values($grupos));
    }

    /**
     * @param  array{x0: int, y0: int, x1: int, y1: int, texto: string}  $a
     * @param  array{x0: int, y0: int, x1: int, y1: int, texto: string}  $b
     */
    private function eAdjacente(array $a, array $b, float $limiarGutter, float $limiarVertical): bool
    {
        $sobreposicaoY = min($a['y1'], $b['y1']) - max($a['y0'], $b['y0']) > 0;
        $sobreposicaoX = min($a['x1'], $b['x1']) - max($a['x0'], $b['x0']) > 0;
        $intervaloX = max($a['x0'], $b['x0']) - min($a['x1'], $b['x1']);
        $intervaloY = max($a['y0'], $b['y0']) - min($a['y1'], $b['y1']);

        $adjacenteHorizontal = $sobreposicaoY && $intervaloX < $limiarGutter;
        $adjacenteVertical = $sobreposicaoX && $intervaloY < $limiarVertical;

        return $adjacenteHorizontal || $adjacenteVertical;
    }

    /**
     * @param  array<int, int>  $raiz
     */
    private function encontrar(array &$raiz, int $indice): int
    {
        while ($raiz[$indice] !== $indice) {
            $raiz[$indice] = $raiz[$raiz[$indice]];
            $indice = $raiz[$indice];
        }

        return $indice;
    }

    /**
     * @param  non-empty-list<array{x0: int, y0: int, x1: int, y1: int, texto: string}>  $palavras
     * @return array{x0: int, y0: int, x1: int, y1: int, texto: string}
     */
    private function consolidar(array $palavras): array
    {
        return [
            'x0' => min(array_column($palavras, 'x0')),
            'y0' => min(array_column($palavras, 'y0')),
            'x1' => max(array_column($palavras, 'x1')),
            'y1' => max(array_column($palavras, 'y1')),
            'texto' => $this->montarTextoEmOrdemDeLeitura($palavras),
        ];
    }

    /**
     * Ordena as palavras da região em ordem de leitura: por linha (palavras que se
     * sobrepõem verticalmente pertencem à mesma linha) e, dentro da linha, por X. Sem
     * isto, palavras à mesma altura com `y0` ligeiramente diferente saíam trocadas
     * (ex.: "Data: Venc.: 2026-07-18 2026-08-17" em vez de "Data: 2026-07-18 Venc.: ...").
     *
     * @param  non-empty-list<array{x0: int, y0: int, x1: int, y1: int, texto: string}>  $palavras
     */
    private function montarTextoEmOrdemDeLeitura(array $palavras): string
    {
        usort($palavras, static fn (array $a, array $b): int => $a['y0'] <=> $b['y0']);

        /** @var list<non-empty-list<array{x0: int, y0: int, x1: int, y1: int, texto: string}>> $linhas */
        $linhas = [];
        $fundoLinha = null;

        foreach ($palavras as $palavra) {
            if ($fundoLinha === null || $palavra['y0'] >= $fundoLinha) {
                $linhas[] = [];
                $fundoLinha = $palavra['y1'];
            } else {
                $fundoLinha = max($fundoLinha, $palavra['y1']);
            }

            $linhas[count($linhas) - 1][] = $palavra;
        }

        $segmentos = array_map(static function (array $linha): string {
            usort($linha, static fn (array $a, array $b): int => $a['x0'] <=> $b['x0']);

            return implode(' ', array_column($linha, 'texto'));
        }, $linhas);

        return implode(' ', $segmentos);
    }

    /**
     * @param  non-empty-list<array{x0: int, y0: int, x1: int, y1: int, texto: string}>  $palavras
     */
    private function calcularAlturaMediana(array $palavras): float
    {
        $alturas = array_map(static fn (array $p): int => $p['y1'] - $p['y0'], $palavras);
        sort($alturas);
        $meio = intdiv(count($alturas), 2);

        return max(1, $alturas[$meio]);
    }
}
