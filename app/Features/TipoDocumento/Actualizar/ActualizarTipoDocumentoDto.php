<?php

declare(strict_types=1);

namespace App\Features\TipoDocumento\Actualizar;

use App\Shared\Enums\PosicaoEmpresaMae;

final readonly class ActualizarTipoDocumentoDto
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $nome,
        public string $descricao,
        public string $idCategoria,
        public PosicaoEmpresaMae $posicaoEmpresaMae,
        public bool $esperaDataDocumento,
        public bool $esperaFornecedor,
        public bool $esperaCliente,
        public bool $esperaValor,
    ) {
        if (trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }

        if (trim($this->descricao) === '') {
            throw new \InvalidArgumentException('descricao não pode ser vazio.');
        }

        if (trim($this->idCategoria) === '') {
            throw new \InvalidArgumentException('idCategoria não pode ser vazio.');
        }

        if (! $this->esperaDataDocumento && ! $this->esperaFornecedor && ! $this->esperaCliente && ! $this->esperaValor) {
            throw new \InvalidArgumentException('Pelo menos um dos campos espera_* tem de ser true.');
        }
    }
}
