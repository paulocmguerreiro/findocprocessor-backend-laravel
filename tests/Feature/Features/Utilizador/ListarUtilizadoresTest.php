<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

describe('autenticado com permissão', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('devolve lista de utilizadores com estrutura paginada', function (): void {
        User::factory()->count(3)->create();

        $this->getJson('/api/utilizadores')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'roles', 'deleted_at', 'created_at']],
                'links' => ['prev', 'next'],
                'meta' => ['per_page', 'next_cursor', 'prev_cursor', 'path'],
            ]);
    });

    it('respeita o parâmetro per_page', function (): void {
        User::factory()->count(5)->create();

        $this->getJson('/api/utilizadores?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    });

    it('por omissão devolve apenas utilizadores activos', function (): void {
        User::factory()->inativo()->count(2)->create();

        $resposta = $this->getJson('/api/utilizadores')->assertOk();

        // apenas o próprio admin autenticado (activo); os 2 inactivos ficam de fora
        expect($resposta->json('data'))->toHaveCount(1);
    });

    it('estado=somente_inativos devolve apenas os inactivos', function (): void {
        User::factory()->inativo()->count(2)->create();

        $this->getJson('/api/utilizadores?estado=somente_inativos')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('estado=todos devolve activos e inactivos', function (): void {
        User::factory()->inativo()->count(2)->create();
        User::factory()->count(1)->create();

        // 2 inactivos + 1 activo + o próprio admin = 4
        $this->getJson('/api/utilizadores?estado=todos')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    });

    it('rejeita estado inválido com erro de validação', function (): void {
        $this->getJson('/api/utilizadores?estado=invalido')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);
    });

    it('rejeita per_page acima do máximo', function (): void {
        $this->getJson('/api/utilizadores?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    criarEAutenticarUtilizador();

    $this->getJson('/api/utilizadores')
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $this->getJson('/api/utilizadores')
        ->assertUnauthorized();
});
