<?php

declare(strict_types=1);

namespace App\Features\Documento\Corrigir;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Correcção de um Documento `Processado` — apenas campos de domínio. O ficheiro
 * nunca é alterado por correcção (trocar ficheiro = eliminar + novo upload); os
 * campos de storage são derivados pela Action (pode reajustar o nome canónico).
 */
final readonly class CorrigirDocumentoDto
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

    /**
     * @throws InvalidArgumentException
     */
    public static function fromRequest(CorrigirDocumentoRequest $request): self
    {
        /** @var array{id_fornecedor: string, id_cliente: string, id_categoria: string, valor: numeric-string|float|int, data_documento: string} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            idFornecedor: $dadosValidados['id_fornecedor'],
            idCliente: $dadosValidados['id_cliente'],
            idCategoria: $dadosValidados['id_categoria'],
            valor: (float) $dadosValidados['valor'],
            dataDocumento: Carbon::parse($dadosValidados['data_documento']),
        );
    }
}
