<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Criar;

use App\Shared\Enums\TipoMovimento;

final readonly class CriarCategoriaDto
{
    public function __construct(
        public string $nome,
        public string $slug,
        public TipoMovimento $tipoMovimento,
    ) {}

    /**
     * @throws \UnexpectedValueException
     */
    public static function fromRequest(CriarCategoriaRequest $request): self
    {
        /** @var array{nome: string, slug: string, tipo_movimento: string} $dadosValidados */
        $dadosValidados = $request->validated();
        $nome = $dadosValidados['nome'] ?? null;
        $slug = $dadosValidados['slug'] ?? null;
        $tipoMovimento = $dadosValidados['tipo_movimento'] ?? null;

        if (! is_string($nome) || ! is_string($slug) || ! is_string($tipoMovimento)) {
            throw new \UnexpectedValueException('Dados inválidos após validação.');
        }

        return new self(
            nome: $nome,
            slug: $slug,
            tipoMovimento: TipoMovimento::from($tipoMovimento),
        );
    }
}
