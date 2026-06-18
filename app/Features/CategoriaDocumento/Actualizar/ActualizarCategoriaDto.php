<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Actualizar;

use App\Shared\Enums\TipoMovimento;

final readonly class ActualizarCategoriaDto
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public ?string $nome,
        public ?string $slug,
        public ?TipoMovimento $tipoMovimento,
    ) {
        if ($this->nome !== null && trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }

        if ($this->slug !== null && trim($this->slug) === '') {
            throw new \InvalidArgumentException('slug não pode ser vazio.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromRequest(ActualizarCategoriaRequest $request): self
    {
        /** @var array{nome?: string, slug?: string, tipo_movimento?: string} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            nome: $dadosValidados['nome'] ?? null,
            slug: $dadosValidados['slug'] ?? null,
            tipoMovimento: isset($dadosValidados['tipo_movimento'])
                ? TipoMovimento::from($dadosValidados['tipo_movimento'])
                : null,
        );
    }
}
