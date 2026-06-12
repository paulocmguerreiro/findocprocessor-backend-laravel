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

    public static function fromRequest(ActualizarCategoriaRequest $request): self
    {
        return new self(
            nome: $request->has('nome') ? $request->string('nome')->toString() : null,
            slug: $request->has('slug') ? $request->string('slug')->toString() : null,
            tipo_movimento: $request->has('tipo_movimento')
                ? TipoMovimento::from($request->string('tipo_movimento')->toString())
                : null,
        );
    }
}
