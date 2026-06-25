<?php

declare(strict_types=1);

namespace App\Features\Documento\Criar;

final readonly class CriarDocumentoManualDto
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $idFornecedor,
        public string $idCliente,
        public string $idCategoria,
        public float $valor,
        public \DateTimeInterface $dataDocumento,
        public string $nomeFicheiroOriginal,
        public string $discoStorage,
        public string $nomeFicheiroStorage,
        public string $hashSha256,
    ) {
        if (trim($this->idFornecedor) === '') {
            throw new \InvalidArgumentException('idFornecedor não pode ser vazio.');
        }

        if (trim($this->idCliente) === '') {
            throw new \InvalidArgumentException('idCliente não pode ser vazio.');
        }

        if (trim($this->idCategoria) === '') {
            throw new \InvalidArgumentException('idCategoria não pode ser vazio.');
        }

        if ($this->valor < 0) {
            throw new \InvalidArgumentException('valor não pode ser negativo.');
        }

        if (trim($this->nomeFicheiroOriginal) === '') {
            throw new \InvalidArgumentException('nomeFicheiroOriginal não pode ser vazio.');
        }

        if (trim($this->discoStorage) === '') {
            throw new \InvalidArgumentException('discoStorage não pode ser vazio.');
        }

        if (trim($this->nomeFicheiroStorage) === '') {
            throw new \InvalidArgumentException('nomeFicheiroStorage não pode ser vazio.');
        }

        if (strlen($this->hashSha256) !== 64) {
            throw new \InvalidArgumentException('hashSha256 tem de ter exactamente 64 caracteres.');
        }
    }
}
