<?php

declare(strict_types=1);

namespace App\Features\Entidade\Criar;

final readonly class CriarEntidadeDto
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

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromRequest(CriarEntidadeRequest $request): self
    {
        /** @var array{nome: string, nif: string, e_cliente: bool, e_fornecedor: bool, e_empresa_aplicacao: bool} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            nome: $dadosValidados['nome'],
            nif: $dadosValidados['nif'],
            eCliente: (bool) $dadosValidados['e_cliente'],
            eFornecedor: (bool) $dadosValidados['e_fornecedor'],
            eEmpresaAplicacao: (bool) $dadosValidados['e_empresa_aplicacao'],
        );
    }
}
