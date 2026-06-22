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
    $utilizador = User::factory()->create();
    $utilizador->assignRole('utilizador');
    Sanctum::actingAs($utilizador, ['api']);

    $this->patchJson("/api/entidades/{$entidade->id}/empresa-mae")
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $entidade = Entidade::factory()->create();

    $this->patchJson("/api/entidades/{$entidade->id}/empresa-mae")
        ->assertUnauthorized();
});
