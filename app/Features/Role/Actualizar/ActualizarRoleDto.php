<?php

declare(strict_types=1);

namespace App\Features\Role\Actualizar;

final readonly class ActualizarRoleDto
{
    /**
     * @param  array<int, string>  $permissoes
     */
    public function __construct(
        public ?string $nome,
        public array $permissoes,
    ) {}

    public static function fromRequest(ActualizarRoleRequest $request): self
    {
        /** @var array{nome?: string, permissoes: array<int, string>} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            nome: $dadosValidados['nome'] ?? null,
            permissoes: $dadosValidados['permissoes'],
        );
    }
}
