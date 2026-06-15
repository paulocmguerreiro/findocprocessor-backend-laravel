<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Actualizar;

use App\Shared\Enums\TipoMovimento;

final readonly class ActualizarCategoriaDto
{
    public function __construct(
        public ?string $nome,
        public ?string $slug,
        public ?TipoMovimento $tipo_movimento,
    ) {}

    /**
     * @throws \UnexpectedValueException
     */
    public static function fromRequest(ActualizarCategoriaRequest $request): self
    {
        /** @var array{nome?: string, slug?: string, tipo_movimento?: string} $validated */
        $validated = $request->validated();
        $nome = $validated['nome'] ?? null;
        $slug = $validated['slug'] ?? null;
        $tipoMovimento = $validated['tipo_movimento'] ?? null;

        if (
            ($nome !== null && ! is_string($nome)) ||
            ($slug !== null && ! is_string($slug)) ||
            ($tipoMovimento !== null && ! is_string($tipoMovimento))
        ) {
            throw new \UnexpectedValueException('Dados inválidos após validação.');
        }

        return new self(
            nome: $nome,
            slug: $slug,
            tipo_movimento: is_string($tipoMovimento) ? TipoMovimento::from($tipoMovimento) : null,
        );
    }
}
