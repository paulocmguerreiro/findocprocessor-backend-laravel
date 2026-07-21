<?php

declare(strict_types=1);

namespace App\Shared\States;

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;

final readonly class DocumentoProcessado implements EstadoDocumentoInterface
{
    public function __construct(
        private string $id,
        private string $discoStorage,
        private string $nomeFicheiroStorage,
        private string $nomeFicheiroOriginal,
        private string $hashSha256,
        private ?string $idFornecedor,
        private ?string $idCliente,
        private ?string $idCategoria,
        private ?string $valor,
        private ?\DateTimeInterface $dataDocumento,
    ) {}

    public static function deDocumento(Documento $documento): self
    {
        return new self(
            id: $documento->id,
            discoStorage: $documento->disco_storage,
            nomeFicheiroStorage: $documento->nome_ficheiro_storage,
            nomeFicheiroOriginal: $documento->nome_ficheiro_original,
            hashSha256: $documento->hash_sha256,
            idFornecedor: $documento->id_fornecedor,
            idCliente: $documento->id_cliente,
            idCategoria: $documento->id_categoria,
            valor: $documento->valor,
            dataDocumento: $documento->data_documento,
        );
    }

    public function obterEstado(): EstadoDocumento
    {
        return EstadoDocumento::Processado;
    }

    public function obterId(): string
    {
        return $this->id;
    }

    public function obterDiscoStorage(): string
    {
        return $this->discoStorage;
    }

    public function obterNomeFicheiroStorage(): string
    {
        return $this->nomeFicheiroStorage;
    }

    public function obterNomeFicheiroOriginal(): string
    {
        return $this->nomeFicheiroOriginal;
    }

    public function obterHashSha256(): string
    {
        return $this->hashSha256;
    }

    public function obterIdFornecedor(): ?string
    {
        return $this->idFornecedor;
    }

    public function obterIdCliente(): ?string
    {
        return $this->idCliente;
    }

    public function obterIdCategoria(): ?string
    {
        return $this->idCategoria;
    }

    public function obterValor(): ?string
    {
        return $this->valor;
    }

    public function obterDataDocumento(): ?\DateTimeInterface
    {
        return $this->dataDocumento;
    }
}
