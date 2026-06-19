<?php

declare(strict_types=1);

use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('autenticado', function (): void {
    beforeEach(function (): void {
        Sanctum::actingAs(User::factory()->create(), ['api']);
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

it('guest sem token recebe 401', function (): void {
    $entidade = Entidade::factory()->create();

    $this->getJson("/api/entidades/{$entidade->id}")
        ->assertUnauthorized();
});
