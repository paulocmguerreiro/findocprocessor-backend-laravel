<?php

declare(strict_types=1);

use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('autenticado', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('converte entidade em empresa mãe e força os três flags', function (): void {
        $entidade = Entidade::factory()->create([
            'e_cliente' => false,
            'e_fornecedor' => false,
            'e_empresa_aplicacao' => false,
        ]);

        $this->patchJson("/api/entidades/{$entidade->id}/empresa-mae")
            ->assertOk()
            ->assertJsonPath('data.e_empresa_aplicacao', true)
            ->assertJsonPath('data.e_cliente', true)
            ->assertJsonPath('data.e_fornecedor', true);

        $this->assertDatabaseHas('entidades', [
            'id' => $entidade->id,
            'e_empresa_aplicacao' => true,
            'e_cliente' => true,
            'e_fornecedor' => true,
        ]);
    });

    it('remove a marcação da empresa mãe anterior ao converter uma nova', function (): void {
        $empresaAnterior = Entidade::factory()->empresaAplicacao()->create();
        $novaEmpresa = Entidade::factory()->create();

        $this->patchJson("/api/entidades/{$novaEmpresa->id}/empresa-mae")
            ->assertOk()
            ->assertJsonPath('data.e_empresa_aplicacao', true);

        $this->assertDatabaseHas('entidades', ['id' => $empresaAnterior->id, 'e_empresa_aplicacao' => false]);
        $this->assertDatabaseHas('entidades', ['id' => $novaEmpresa->id, 'e_empresa_aplicacao' => true]);
    });

    it('devolve 404 quando UUID não existe', function (): void {
        $this->patchJson('/api/entidades/00000000-0000-0000-0000-000000000000/empresa-mae')
            ->assertNotFound();
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $entidade = Entidade::factory()->create();
    criarEAutenticarUtilizador();

    $this->patchJson("/api/entidades/{$entidade->id}/empresa-mae")
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $entidade = Entidade::factory()->create();

    $this->patchJson("/api/entidades/{$entidade->id}/empresa-mae")
        ->assertUnauthorized();
});
