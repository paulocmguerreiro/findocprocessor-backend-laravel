<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\Transicao;

use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Invariante de domínio: gera o nome canónico de um Documento processado —
 * `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}` — preservando a extensão
 * do ficheiro original. Usado pela transição para `Processado` e pela correcção.
 */
final readonly class RegraNomearProcessado
{
    public function handle(
        DateTimeInterface $dataDocumento,
        string $nomeFornecedor,
        string $nomeCategoria,
        string $nomeFicheiroOriginal,
    ): string {
        $base = sprintf(
            '%s-%s-%s',
            $dataDocumento->format('Y-m-d'),
            Str::slug($nomeFornecedor),
            Str::slug($nomeCategoria),
        );

        $extensao = Str::lower(pathinfo($nomeFicheiroOriginal, PATHINFO_EXTENSION));

        return $extensao === '' ? $base : "{$base}.{$extensao}";
    }
}
