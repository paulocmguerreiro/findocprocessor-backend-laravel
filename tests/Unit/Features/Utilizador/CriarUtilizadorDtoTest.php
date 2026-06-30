<?php

declare(strict_types=1);

use App\Features\Utilizador\Criar\CriarUtilizadorDto;
use App\Features\Utilizador\Criar\CriarUtilizadorRequest;

describe('Construtor', function (): void {
    it('lança InvalidArgumentException se nome for vazio', function (): void {
        expect(fn (): CriarUtilizadorDto => new CriarUtilizadorDto(
            nome: '   ',
            email: 'a@example.com',
            password: 'Abc12345!',
            role: null,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se email for vazio', function (): void {
        expect(fn (): CriarUtilizadorDto => new CriarUtilizadorDto(
            nome: 'Nome',
            email: '   ',
            password: 'Abc12345!',
            role: null,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se password tiver menos de 8 caracteres', function (): void {
        expect(fn (): CriarUtilizadorDto => new CriarUtilizadorDto(
            nome: 'Nome',
            email: 'a@example.com',
            password: 'Ab1!',
            role: null,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('cria DTO com dados válidos', function (): void {
        $dto = new CriarUtilizadorDto(
            nome: 'Maria',
            email: 'maria@example.com',
            password: 'Abc12345!',
            role: 'admin',
        );

        expect($dto->nome)->toBe('Maria')
            ->and($dto->email)->toBe('maria@example.com')
            ->and($dto->password)->toBe('Abc12345!')
            ->and($dto->role)->toBe('admin');
    });
});

describe('fromRequest()', function (): void {
    it('cria DTO a partir de request válido com role', function (): void {
        $request = Mockery::mock(CriarUtilizadorRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'password' => 'Abc12345!',
            'role' => 'admin',
        ]);

        $dto = CriarUtilizadorDto::fromRequest($request);

        expect($dto->nome)->toBe('Maria')
            ->and($dto->role)->toBe('admin');
    });

    it('role fica null quando ausente', function (): void {
        $request = Mockery::mock(CriarUtilizadorRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'password' => 'Abc12345!',
        ]);

        expect(CriarUtilizadorDto::fromRequest($request)->role)->toBeNull();
    });
});
