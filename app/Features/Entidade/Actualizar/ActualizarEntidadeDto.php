<?php

declare(strict_types=1);

namespace App\Features\Entidade\Actualizar;

final readonly class ActualizarEntidadeDto
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $nome,
        public string $nif,
        public bool $eCliente,
        public bool $eFornecedor,
        public bool $eEmpresaAplicacao,
    ) {
        if (trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }

        if (trim($this->nif) === '') {
            throw new \InvalidArgumentException('nif não pode ser vazio.');
        }
    }
}
