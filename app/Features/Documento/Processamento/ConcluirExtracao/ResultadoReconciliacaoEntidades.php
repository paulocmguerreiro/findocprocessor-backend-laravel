<?php

declare(strict_types=1);

namespace App\Features\Documento\Processamento\ConcluirExtracao;

/**
 * Resultado de `RegraReconciliarEntidadesDocumento::handle()`: os IDs de `Entidade`
 * resolvidos por lado (`null` quando o lado não é esperado nem empresa mãe), a
 * categoria (sempre herdada do `TipoDocumento`) e o nome do fornecedor a usar no
 * nome canónico do ficheiro (nome extraído pela IA; nome da empresa mãe como
 * fallback quando a extracção vem vazia — RN-03).
 */
final readonly class ResultadoReconciliacaoEntidades
{
    public function __construct(
        public ?string $idFornecedor,
        public ?string $idCliente,
        public string $idCategoria,
        public string $nomeFornecedorParaNome,
    ) {}
}
