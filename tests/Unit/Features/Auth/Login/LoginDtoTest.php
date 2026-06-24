<?php

declare(strict_types=1);

use App\Features\Auth\Login\LoginDto;

describe('Construtor', function (): void {
    it('lança InvalidArgumentException se email for vazio', function (): void {
        expect(fn (): LoginDto => new LoginDto(
            email: '',
            password: 'password',
            ip: '127.0.0.1',
            agente: 'test',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se password for vazia', function (): void {
        expect(fn (): LoginDto => new LoginDto(
            email: 'test@test.pt',
            password: '',
            ip: '127.0.0.1',
            agente: 'test',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('cria DTO com dados válidos', function (): void {
        $dto = new LoginDto(
            email: 'test@test.pt',
            password: 'password',
            ip: '127.0.0.1',
            agente: 'Mozilla/5.0',
        );

        expect($dto->email)->toBe('test@test.pt')
            ->and($dto->password)->toBe('password')
            ->and($dto->ip)->toBe('127.0.0.1')
            ->and($dto->agente)->toBe('Mozilla/5.0');
    });

    it('usa "desconhecido" como fallback para ip e agente ausentes', function (): void {
        $dto = new LoginDto(
            email: 'test@test.pt',
            password: 'password',
            ip: 'desconhecido',
            agente: 'desconhecido',
        );

        expect($dto->ip)->toBe('desconhecido')
            ->and($dto->agente)->toBe('desconhecido');
    });
});
