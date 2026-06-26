<?php

declare(strict_types=1);

namespace App\Features\Documento\Criar;

use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Registo manual de um documento já tratado: campos de domínio + o ficheiro.
 * Os campos de storage (`hash`, disco, nome) são derivados pela Action — não
 * são fornecidos pelo cliente. Exposta via HTTP — `fromRequest` na camada HTTP.
 */
final readonly class RegistarDocumentoManualDto
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
        public UploadedFile $ficheiro,
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
