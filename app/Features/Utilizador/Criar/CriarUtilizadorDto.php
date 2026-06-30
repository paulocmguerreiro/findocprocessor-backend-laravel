<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Criar;

final readonly class CriarUtilizadorDto
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $nome,
        public string $email,
        public string $password,
        public ?string $role,
    ) {
        if (trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }

        if (trim($this->email) === '') {
            throw new \InvalidArgumentException('email não pode ser vazio.');
        }

        if (strlen($this->password) < 8) {
            throw new \InvalidArgumentException('password deve ter pelo menos 8 caracteres.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromRequest(CriarUtilizadorRequest $request): self
    {
        /** @var array{name: string, email: string, password: string, role?: string|null} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            nome: $dadosValidados['name'],
            email: $dadosValidados['email'],
            password: $dadosValidados['password'],
            role: $dadosValidados['role'] ?? null,
        );
    }
}
