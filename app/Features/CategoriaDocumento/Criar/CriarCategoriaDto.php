<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Criar;

use App\Shared\Enums\TipoMovimento;

final readonly class CriarCategoriaDto
{
    public function __construct(
        public string $nome,
        public string $slug,
        public TipoMovimento $tipo_movimento,
    ) {}

    public static function fromRequest(CriarCategoriaRequest $request): self
    {
        return new self(
            nome: $request->string('nome')->toString(),
            slug: $request->string('slug')->toString(),
            tipo_movimento: TipoMovimento::from($request->string('tipo_movimento')->toString()),
        );
    }
}
