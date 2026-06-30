<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

function payloadUtilizadorValido(array $sobrepor = []): array
{
    return array_merge([
        'name' => 'Maria Silva',
        'email' => 'maria.silva@example.com',
        'password' => 'Abc12345!',
        'password_confirmation' => 'Abc12345!',
    ], $sobrepor);
}

describe('autenticado com permissão', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('cria utilizador e devolve 201 com password protegida', function (): void {
        $this->postJson('/api/utilizadores', payloadUtilizadorValido())
            ->assertCreated()
            ->assertJsonPath('data.email', 'maria.silva@example.com')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'roles', 'created_at']]);

        $this->assertDatabaseHas('users', ['email' => 'maria.silva@example.com']);

        $criado = User::where('email', 'maria.silva@example.com')->firstOrFail();
        expect($criado->password)->not->toBe('Abc12345!')
            ->and(Hash::check('Abc12345!', $criado->password))->toBeTrue();
    });

    it('cria utilizador com role atribuído', function (): void {
        $this->postJson('/api/utilizadores', payloadUtilizadorValido(['role' => 'utilizador']))
            ->assertCreated()
            ->assertJsonPath('data.roles', ['utilizador']);

        $criado = User::where('email', 'maria.silva@example.com')->firstOrFail();
        expect($criado->hasRole('utilizador'))->toBeTrue();
    });

    it('rejeita email duplicado com 422', function (): void {
        User::factory()->create(['email' => 'maria.silva@example.com']);

        $this->postJson('/api/utilizadores', payloadUtilizadorValido())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('rejeita password fraca com 422', function (): void {
        $this->postJson('/api/utilizadores', payloadUtilizadorValido([
            'password' => 'fraca',
            'password_confirmation' => 'fraca',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    it('rejeita role inexistente com 422', function (): void {
        $this->postJson('/api/utilizadores', payloadUtilizadorValido(['role' => 'inexistente']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    criarEAutenticarUtilizador();

    $this->postJson('/api/utilizadores', payloadUtilizadorValido())
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $this->postJson('/api/utilizadores', payloadUtilizadorValido())
        ->assertUnauthorized();
});
