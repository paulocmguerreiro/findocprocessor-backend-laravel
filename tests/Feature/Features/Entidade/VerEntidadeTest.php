<?php

declare(strict_types=1);

use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

describe('autenticado', function (): void {
    beforeEach(function (): void {
        $utilizador = User::factory()->create();
        $utilizador->assignRole('admin');
        Sanctum::actingAs($utilizador, ['api']);
    });

    it('devolve entidade existente com estrutura correcta', function (): void {
        $entidade = Entidade::factory()->cliente()->create();

        $this->getJson("/api/entidades/{$entidade->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'nome', 'nif', 'e_cliente', 'e_fornecedor', 'e_empresa_aplicacao'],
            ])
            ->assertJsonPath('data.id', $entidade->id)
            ->assertJsonPath('data.nome', $entidade->nome);
    });

    it('devolve 404 quando UUID não existe', function (): void {
        $this->getJson('/api/entidades/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    });
});

it('utilizador com permissão de leitura devolve 200', function (): void {
    $entidade = Entidade::factory()->create();
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    Sanctum::actingAs($utilizador, ['api']);

    $this->getJson("/api/entidades/{$entidade->id}")
        ->assertOk();
});

it('guest sem token recebe 401', function (): void {
    $entidade = Entidade::factory()->create();

    $this->getJson("/api/entidades/{$entidade->id}")
        ->assertUnauthorized();
});
