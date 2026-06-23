<?php

declare(strict_types=1);

namespace App\Features\Role\Criar;

final readonly class CriarRoleDto
{
    /**
     * @param  array<int, string>  $permissoes
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $nome,
        public array $permissoes,
    ) {
        if (trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromRequest(CriarRoleRequest $request): self
    {
        /** @var array{nome: string, permissoes: array<int, string>} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            nome: $dadosValidados['nome'],
            permissoes: $dadosValidados['permissoes'],
        );
    }
}
