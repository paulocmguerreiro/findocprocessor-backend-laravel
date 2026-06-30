<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Actualizar;

final readonly class ActualizarUtilizadorDto
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $nome,
        public string $email,
        public ?string $password,
    ) {
        if (trim($this->nome) === '') {
            throw new \InvalidArgumentException('nome não pode ser vazio.');
        }

        if (trim($this->email) === '') {
            throw new \InvalidArgumentException('email não pode ser vazio.');
        }

        if ($this->password !== null && strlen($this->password) < 8) {
            throw new \InvalidArgumentException('password deve ter pelo menos 8 caracteres.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromRequest(ActualizarUtilizadorRequest $request): self
    {
        /** @var array{name: string, email: string, password?: string|null} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            nome: $dadosValidados['name'],
            email: $dadosValidados['email'],
            password: $dadosValidados['password'] ?? null,
        );
    }
}
