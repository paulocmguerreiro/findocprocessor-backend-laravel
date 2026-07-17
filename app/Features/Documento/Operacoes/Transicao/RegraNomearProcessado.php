<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\Transicao;

use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Invariante de domínio: gera o nome canónico de um Documento processado —
 * `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}` — preservando a extensão
 * do ficheiro original. Usado pela transição para `Processado` e pela correcção.
 *
 * Fallbacks para documentos parciais (extrato, aviso): `dataDocumento` nula →
 * `createdAt` do documento; `nomeFornecedor` nulo/vazio (fornecedor não
 * reconciliado como `Entidade`) → `nomeFornecedorExtraido` (o nome que a IA leu,
 * já com o nome da empresa mãe injectado a montante quando a extracção vem vazia).
 */
final readonly class RegraNomearProcessado
{
    public function handle(
        ?DateTimeInterface $dataDocumento,
        ?string $nomeFornecedor,
        ?string $nomeFornecedorExtraido,
        string $nomeCategoria,
        string $nomeFicheiroOriginal,
        DateTimeInterface $createdAt,
    ): string {
        $data = $dataDocumento ?? $createdAt;

        $nomeParaSlug = $nomeFornecedor !== null && trim($nomeFornecedor) !== ''
            ? $nomeFornecedor
            : (string) $nomeFornecedorExtraido;

        $base = sprintf(
            '%s-%s-%s',
            $data->format('Y-m-d'),
            Str::slug($nomeParaSlug),
            Str::slug($nomeCategoria),
        );

        $extensao = Str::lower(pathinfo($nomeFicheiroOriginal, PATHINFO_EXTENSION));

        return $extensao === '' ? $base : "{$base}.{$extensao}";
    }
}
