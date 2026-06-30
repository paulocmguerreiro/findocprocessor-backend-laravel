<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['utilizadores'])->flush());

describe('autenticado com permissão', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('actualiza nome e email e devolve 200', function (): void {
        $alvo = User::factory()->create(['name' => 'Antigo', 'email' => 'antigo@example.com']);

        $this->putJson("/api/utilizadores/{$alvo->id}", [
            'name' => 'Novo Nome',
            'email' => 'novo@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo Nome')
            ->assertJsonPath('data.email', 'novo@example.com');

        $this->assertDatabaseHas('users', ['id' => $alvo->id, 'email' => 'novo@example.com']);
    });

    it('sem password não altera a password existente', function (): void {
        $alvo = User::factory()->create();
        $hashOriginal = $alvo->password;

        $this->putJson("/api/utilizadores/{$alvo->id}", [
            'name' => 'Outro Nome',
            'email' => $alvo->email,
        ])->assertOk();

        expect($alvo->fresh()->password)->toBe($hashOriginal);
    });

    it('com password actualiza-a de forma protegida', function (): void {
        $alvo = User::factory()->create();

        $this->putJson("/api/utilizadores/{$alvo->id}", [
            'name' => $alvo->name,
            'email' => $alvo->email,
            'password' => 'NovaPass1!',
            'password_confirmation' => 'NovaPass1!',
        ])->assertOk();

        $actualizado = $alvo->fresh();
        expect($actualizado->password)->not->toBe('NovaPass1!')
            ->and(Hash::check('NovaPass1!', $actualizado->password))->toBeTrue();
    });

    it('actualiza utilizador inactivo (soft-deleted) e devolve 200', function (): void {
        $alvo = User::factory()->inativo()->create(['name' => 'Inactivo']);

        $this->putJson("/api/utilizadores/{$alvo->id}", [
            'name' => 'Reactivado Nome',
            'email' => $alvo->email,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Reactivado Nome');
    });

    it('rejeita email duplicado de outro utilizador com 422', function (): void {
        User::factory()->create(['email' => 'ocupado@example.com']);
        $alvo = User::factory()->create();

        $this->putJson("/api/utilizadores/{$alvo->id}", [
            'name' => $alvo->name,
            'email' => 'ocupado@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('aceita manter o próprio email (ignore na regra unique)', function (): void {
        $alvo = User::factory()->create(['email' => 'meu@example.com']);

        $this->putJson("/api/utilizadores/{$alvo->id}", [
            'name' => 'Nome Novo',
            'email' => 'meu@example.com',
        ])->assertOk();
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    criarEAutenticarUtilizador();
    $alvo = User::factory()->create();

    $this->putJson("/api/utilizadores/{$alvo->id}", [
        'name' => 'X',
        'email' => 'x@example.com',
    ])->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $alvo = User::factory()->create();

    $this->putJson("/api/utilizadores/{$alvo->id}", [
        'name' => 'X',
        'email' => 'x@example.com',
    ])->assertUnauthorized();
});
