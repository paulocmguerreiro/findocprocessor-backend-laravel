<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\TransicoesEstado;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Dados de domínio extraídos para a transição `AnaliseIaLocal|AnaliseCloud →
 * Processado`. Construído programaticamente pelo pipeline (orquestradores de
 * extracção) — sem `fromRequest`.
 *
 * Campos de entidade (`idFornecedor`/`idCliente`), `valor` e `dataDocumento` são
 * nullable: documentos parciais (extrato, aviso) só têm um dos lados preenchido
 * (o da empresa mãe). Invariante mínima: pelo menos um dos lados de entidade
 * preenchido; `idCategoria` continua obrigatório. `nomeFornecedorExtraido`
 * alimenta o fallback de nome canónico quando `idFornecedor` é nulo.
 */
final readonly class TransicionarProcessadoDocumentoDto
{
    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        public ?string $idFornecedor,
        public ?string $idCliente,
        public string $idCategoria,
        public ?float $valor,
        public ?DateTimeInterface $dataDocumento,
        public ?string $nomeFornecedorExtraido = null,
    ) {
        $temFornecedor = $this->idFornecedor !== null && trim($this->idFornecedor) !== '';
        $temCliente = $this->idCliente !== null && trim($this->idCliente) !== '';

        if (! $temFornecedor && ! $temCliente) {
            throw new InvalidArgumentException('Pelo menos um dos lados (fornecedor ou cliente) tem de estar preenchido.');
        }

        if ($this->idFornecedor !== null && trim($this->idFornecedor) === '') {
            throw new InvalidArgumentException('idFornecedor não pode ser vazio.');
        }

        if ($this->idCliente !== null && trim($this->idCliente) === '') {
            throw new InvalidArgumentException('idCliente não pode ser vazio.');
        }

        if (trim($this->idCategoria) === '') {
            throw new InvalidArgumentException('idCategoria não pode ser vazio.');
        }

        if ($this->valor !== null && $this->valor < 0) {
            throw new InvalidArgumentException('valor não pode ser negativo.');
        }
    }
}
