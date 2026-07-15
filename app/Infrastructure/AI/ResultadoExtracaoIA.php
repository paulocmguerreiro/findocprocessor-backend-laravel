<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Models\TipoDocumento;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Value Object do veredicto de `ContratoClienteIA::extrair()`: `completo` (dados
 * normalizados + `TipoDocumento` resolvido + categoria derivada),
 * `desconhecido` (`tipo_documento` não resolúvel), `perigoso` (regra 7 do
 * prompt), `incompleto` (falta um campo `espera_*=true` ou NIF/Nome
 * inválido) ou `falhaTecnica` (excepção do Prism, nunca propagada).
 * Construtor privado — só acessível via as factories, garantindo que nunca
 * existe num estado ambíguo (ex.: perigoso sem motivo). Propriedades
 * `public readonly` — sem lógica associada à leitura, dispensam getters
 * (os 5 métodos booleanos mantêm-se porque calculam algo, ao contrário de
 * uma simples devolução de valor).
 */
final readonly class ResultadoExtracaoIA
{
    /**
     * @param  list<string>  $motivosFalta
     *
     * @throws InvalidArgumentException
     */
    private function __construct(
        public VeredictoExtracaoIA $veredicto,
        public ?TipoDocumento $tipoDocumento = null,
        public ?string $idCategoria = null,
        public ?DateTimeInterface $dataDocumento = null,
        public ?string $nifFornecedor = null,
        public ?string $nomeFornecedor = null,
        public ?string $nifCliente = null,
        public ?string $nomeCliente = null,
        public ?float $valor = null,
        public ?string $motivo = null,
        public array $motivosFalta = [],
    ) {
        $exigeMotivo = in_array($this->veredicto, [VeredictoExtracaoIA::Perigoso, VeredictoExtracaoIA::FalhaTecnica], true);

        if ($exigeMotivo && trim((string) $this->motivo) === '') {
            throw new InvalidArgumentException('motivo é obrigatório quando o veredicto é perigoso ou falha técnica.');
        }

        if ($this->veredicto === VeredictoExtracaoIA::Incompleto && $this->motivosFalta === []) {
            throw new InvalidArgumentException('motivosFalta não pode ser vazio quando o veredicto é incompleto.');
        }
    }

    public static function completo(
        TipoDocumento $tipoDocumento,
        string $idCategoria,
        ?DateTimeInterface $dataDocumento,
        ?string $nifFornecedor,
        ?string $nomeFornecedor,
        ?string $nifCliente,
        ?string $nomeCliente,
        ?float $valor,
    ): self {
        return new self(
            veredicto: VeredictoExtracaoIA::Completo,
            tipoDocumento: $tipoDocumento,
            idCategoria: $idCategoria,
            dataDocumento: $dataDocumento,
            nifFornecedor: $nifFornecedor,
            nomeFornecedor: $nomeFornecedor,
            nifCliente: $nifCliente,
            nomeCliente: $nomeCliente,
            valor: $valor,
        );
    }

    public static function desconhecido(): self
    {
        return new self(VeredictoExtracaoIA::Desconhecido);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function perigoso(string $motivo): self
    {
        return new self(VeredictoExtracaoIA::Perigoso, motivo: $motivo);
    }

    /**
     * @param  list<string>  $motivosFalta
     *
     * @throws InvalidArgumentException
     */
    public static function incompleto(array $motivosFalta): self
    {
        return new self(VeredictoExtracaoIA::Incompleto, motivosFalta: $motivosFalta);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function falhaTecnica(string $motivo): self
    {
        return new self(VeredictoExtracaoIA::FalhaTecnica, motivo: $motivo);
    }

    public function ehCompleto(): bool
    {
        return $this->veredicto === VeredictoExtracaoIA::Completo;
    }

    public function ehDesconhecido(): bool
    {
        return $this->veredicto === VeredictoExtracaoIA::Desconhecido;
    }

    public function ehPerigoso(): bool
    {
        return $this->veredicto === VeredictoExtracaoIA::Perigoso;
    }

    public function ehIncompleto(): bool
    {
        return $this->veredicto === VeredictoExtracaoIA::Incompleto;
    }

    public function estaEmFalhaTecnica(): bool
    {
        return $this->veredicto === VeredictoExtracaoIA::FalhaTecnica;
    }
}
