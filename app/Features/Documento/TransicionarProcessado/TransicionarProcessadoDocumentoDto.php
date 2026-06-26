<?php

declare(strict_types=1);

namespace App\Features\Documento\TransicionarProcessado;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Dados de domínio extraídos para a transição `AguardaResposta → Processado`.
 * Construído programaticamente pelo pipeline (Job de extracção) — sem `fromRequest`.
 */
final readonly class TransicionarProcessadoDocumentoDto
{
    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $idFornecedor,
        public string $idCliente,
        public string $idCategoria,
        public float $valor,
        public DateTimeInterface $dataDocumento,
    ) {
        if (trim($this->idFornecedor) === '') {
            throw new InvalidArgumentException('idFornecedor não pode ser vazio.');
        }

        if (trim($this->idCliente) === '') {
            throw new InvalidArgumentException('idCliente não pode ser vazio.');
        }

        if (trim($this->idCategoria) === '') {
            throw new InvalidArgumentException('idCategoria não pode ser vazio.');
        }

        if ($this->valor < 0) {
            throw new InvalidArgumentException('valor não pode ser negativo.');
        }
    }
}
