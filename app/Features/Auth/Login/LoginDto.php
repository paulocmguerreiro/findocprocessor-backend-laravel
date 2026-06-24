<?php

declare(strict_types=1);

namespace App\Features\Auth\Login;

final readonly class LoginDto
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $email,
        public string $password,
        public string $ip,
        public string $agente,
    ) {
        if (trim($this->email) === '') {
            throw new \InvalidArgumentException('email não pode ser vazio.');
        }

        if (trim($this->password) === '') {
            throw new \InvalidArgumentException('password não pode ser vazia.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromRequest(LoginRequest $request): self
    {
        /** @var array{email: string, password: string} $dadosValidados */
        $dadosValidados = $request->validated();

        return new self(
            email: $dadosValidados['email'],
            password: $dadosValidados['password'],
            ip: $request->ip() ?? 'desconhecido',
            agente: $request->userAgent() ?? 'desconhecido',
        );
    }
}
