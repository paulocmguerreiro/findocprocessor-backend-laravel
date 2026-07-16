<?php

declare(strict_types=1);

namespace App\Features\Documento\Reprocessar;

/**
 * Parâmetros da transição `Erro → Pendente`. Exposta via HTTP — `fromRequest`
 * adicionado com o FormRequest (camada HTTP).
 */
final readonly class ReprocessarDocumentoDto
{
    public function __construct(public ModoReprocessamento $modo) {}

    public static function fromRequest(ReprocessarDocumentoRequest $request): self
    {
        /** @var array{modo: string} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(ModoReprocessamento::from($dadosValidados['modo']));
    }
}
