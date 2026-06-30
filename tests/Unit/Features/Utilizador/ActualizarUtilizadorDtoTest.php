<?php

declare(strict_types=1);

use App\Features\Utilizador\Actualizar\ActualizarUtilizadorDto;
use App\Features\Utilizador\Actualizar\ActualizarUtilizadorRequest;

describe('Construtor', function (): void {
    it('lança InvalidArgumentException se nome for vazio', function (): void {
        expect(fn (): ActualizarUtilizadorDto => new ActualizarUtilizadorDto(
            nome: '   ',
            email: 'a@example.com',
            password: null,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se email for vazio', function (): void {
        expect(fn (): ActualizarUtilizadorDto => new ActualizarUtilizadorDto(
            nome: 'Nome',
            email: '   ',
            password: null,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se password (não-null) tiver menos de 8 caracteres', function (): void {
        expect(fn (): ActualizarUtilizadorDto => new ActualizarUtilizadorDto(
            nome: 'Nome',
            email: 'a@example.com',
            password: 'Ab1!',
        ))->toThrow(InvalidArgumentException::class);
    });

    it('aceita password null (não altera)', function (): void {
        $dto = new ActualizarUtilizadorDto(nome: 'Nome', email: 'a@example.com', password: null);

        expect($dto->password)->toBeNull();
    });

    it('cria DTO com dados válidos', function (): void {
        $dto = new ActualizarUtilizadorDto(nome: 'Maria', email: 'maria@example.com', password: 'Abc12345!');

        expect($dto->nome)->toBe('Maria')
            ->and($dto->email)->toBe('maria@example.com')
            ->and($dto->password)->toBe('Abc12345!');
    });
});

describe('fromRequest()', function (): void {
    it('cria DTO a partir de request válido', function (): void {
        $request = Mockery::mock(ActualizarUtilizadorRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'password' => 'Abc12345!',
        ]);

        $dto = ActualizarUtilizadorDto::fromRequest($request);

        expect($dto->nome)->toBe('Maria')
            ->and($dto->password)->toBe('Abc12345!');
    });

    it('password fica null quando ausente', function (): void {
        $request = Mockery::mock(ActualizarUtilizadorRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'name' => 'Maria',
            'email' => 'maria@example.com',
        ]);

        expect(ActualizarUtilizadorDto::fromRequest($request)->password)->toBeNull();
    });
});
