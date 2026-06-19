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

    it('actualiza entidade e devolve 200 com o recurso', function (): void {
        $entidade = Entidade::factory()->create(['nome' => 'Nome Original', 'nif' => '111111111']);

        $this->putJson("/api/entidades/{$entidade->id}", [
            'nome' => 'Nome Actualizado',
            'nif' => '222222222',
            'e_cliente' => true,
            'e_fornecedor' => true,
            'e_empresa_aplicacao' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Nome Actualizado')
            ->assertJsonPath('data.nif', '222222222')
            ->assertJsonPath('data.e_cliente', true)
            ->assertJsonPath('data.e_fornecedor', true);

        $this->assertDatabaseHas('entidades', ['id' => $entidade->id, 'nome' => 'Nome Actualizado']);
    });

    it('actualizar com e_empresa_aplicacao=true remove marcação anterior e força flags', function (): void {
        $empresaAnterior = Entidade::factory()->empresaAplicacao()->create();
        $entidade = Entidade::factory()->create();

        $this->putJson("/api/entidades/{$entidade->id}", [
            'nome' => $entidade->nome,
            'nif' => $entidade->nif,
            'e_cliente' => false,
            'e_fornecedor' => false,
            'e_empresa_aplicacao' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.e_empresa_aplicacao', true)
            ->assertJsonPath('data.e_cliente', true)
            ->assertJsonPath('data.e_fornecedor', true);

        $this->assertDatabaseHas('entidades', ['id' => $empresaAnterior->id, 'e_empresa_aplicacao' => false]);
    });

    it('devolve 404 quando UUID não existe', function (): void {
        $this->putJson('/api/entidades/00000000-0000-0000-0000-000000000000', [
            'nome' => 'Teste',
            'nif' => '123456789',
            'e_cliente' => true,
            'e_fornecedor' => false,
            'e_empresa_aplicacao' => false,
        ])->assertNotFound();
    });

    it('devolve 422 quando campos obrigatórios estão em falta', function (): void {
        $entidade = Entidade::factory()->create();

        $this->putJson("/api/entidades/{$entidade->id}", [])
            ->assertUnprocessable()
            ->assertJsonStructure(['status', 'detail', 'errors' => ['nome', 'nif', 'e_cliente', 'e_fornecedor', 'e_empresa_aplicacao']]);
    });
});

it('guest sem token recebe 401', function (): void {
    $entidade = Entidade::factory()->create();

    $this->putJson("/api/entidades/{$entidade->id}", [
        'nome' => 'Teste',
        'nif' => '123456789',
        'e_cliente' => true,
        'e_fornecedor' => false,
        'e_empresa_aplicacao' => false,
    ])->assertUnauthorized();
});
