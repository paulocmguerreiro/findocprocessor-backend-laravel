<?php

declare(strict_types=1);

namespace App\Infrastructure\Extracao;

/**
 * Value Object do resultado de `ExtractorTextoNativo::extrair()` ou
 * `ExtractorOcr::extrair()`: o texto extraído (vazio é um resultado válido,
 * ex. PDF em branco) e o veredicto do threshold de 50 caracteres —
 * preenchido pelo extractor nativo (`comVeredictoThreshold()`), sempre
 * `null` no OCR (`semVeredicto()`, RN-01) porque essa camada não decide
 * threshold. Construtor privado — só acessível via as factories.
 */
final readonly class ResultadoExtracao
{
    private function __construct(
        private string $texto,
        private ?bool $ultrapassaThreshold,
    ) {}

    public static function comVeredictoThreshold(string $texto, bool $ultrapassaThreshold): self
    {
        return new self($texto, $ultrapassaThreshold);
    }

    public static function semVeredicto(string $texto): self
    {
        return new self($texto, null);
    }

    public function texto(): string
    {
        return $this->texto;
    }

    public function ultrapassaThreshold(): ?bool
    {
        return $this->ultrapassaThreshold;
    }
}
