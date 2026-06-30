<?php

declare(strict_types=1);

use App\Features\Utilizador\Criar\CriarUtilizadorAction;
use App\Features\Utilizador\Criar\CriarUtilizadorDto;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('cria o utilizador na BD com password protegida', function (): void {
        $dto = new CriarUtilizadorDto(
            nome: 'João',
            email: 'joao@example.com',
            password: 'Abc12345!',
            role: null,
        );

        $resultado = app(CriarUtilizadorAction::class)->handle($dto);

        expect($resultado->name)->toBe('João')
            ->and(Hash::check('Abc12345!', $resultado->password))->toBeTrue();

        $this->assertDatabaseHas('users', ['email' => 'joao@example.com']);
    });

    it('atribui o role indicado', function (): void {
        $dto = new CriarUtilizadorDto(
            nome: 'João',
            email: 'joao@example.com',
            password: 'Abc12345!',
            role: 'utilizador',
        );

        $resultado = app(CriarUtilizadorAction::class)->handle($dto);

        expect($resultado->hasRole('utilizador'))->toBeTrue();
    });

    it('faz rollback quando ocorre excepção após insert', function (): void {
        User::created(function (): void {
            throw new RuntimeException('falha simulada após insert');
        });

        $dto = new CriarUtilizadorDto(
            nome: 'João',
            email: 'joao@example.com',
            password: 'Abc12345!',
            role: null,
        );

        expect(fn (): User => app(CriarUtilizadorAction::class)->handle($dto))
            ->toThrow(RuntimeException::class, 'falha simulada após insert');

        $this->assertDatabaseMissing('users', ['email' => 'joao@example.com']);
    });
});

it('sem permissão lança AuthorizationException', function (): void {
    $this->actingAs(criarUtilizador());

    $dto = new CriarUtilizadorDto(
        nome: 'João',
        email: 'joao@example.com',
        password: 'Abc12345!',
        role: null,
    );

    expect(fn (): User => app(CriarUtilizadorAction::class)->handle($dto))
        ->toThrow(AuthorizationException::class);
});
