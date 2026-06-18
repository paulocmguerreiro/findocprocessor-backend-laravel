<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Criar;

use App\Shared\Enums\TipoMovimento;

final readonly class CriarCategoriaDto
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $nome,
        public string $slug,
        public TipoMovimento $tipoMovimento,
    ) {
        if (trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }

        if (trim($this->slug) === '') {
            throw new \InvalidArgumentException('slug não pode ser vazio.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromRequest(CriarCategoriaRequest $request): self
    {
        /** @var array{nome: string, slug: string, tipo_movimento: string} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            nome: $dadosValidados['nome'],
            slug: $dadosValidados['slug'],
            tipoMovimento: TipoMovimento::from($dadosValidados['tipo_movimento']),
        );
    }
}
