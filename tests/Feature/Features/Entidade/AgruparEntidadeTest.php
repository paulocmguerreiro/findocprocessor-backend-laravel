<?php

declare(strict_types=1);

use App\Models\Documento;
use App\Models\Entidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('autenticado como admin', function (): void {
    beforeEach(fn (): User => criarEAutenticarAdmin());

    it('agrupa a secundária na principal, une papéis e reponta documentos', function (): void {
        $principal = Entidade::factory()->create(['e_cliente' => false, 'e_fornecedor' => true]);
        $secundaria = Entidade::factory()->create(['e_cliente' => true, 'e_fornecedor' => false]);
        $documento = Documento::factory()->create(['id_fornecedor' => $secundaria->id]);

        $this->postJson("/api/entidades/{$principal->id}/agrupar-com/{$secundaria->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $principal->id)
            ->assertJsonPath('data.e_cliente', true)
            ->assertJsonPath('data.e_fornecedor', true);

        $this->assertDatabaseHas('documentos', ['id' => $documento->id, 'id_fornecedor' => $principal->id]);
        $this->assertDatabaseMissing('entidades', ['id' => $secundaria->id]);
    });

    it('devolve 422 quando principal e secundária são iguais', function (): void {
        $entidade = Entidade::factory()->create();

        $this->postJson("/api/entidades/{$entidade->id}/agrupar-com/{$entidade->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('entidades', ['id' => $entidade->id]);
    });

    it('devolve 422 quando a secundária é a empresa aplicação', function (): void {
        $principal = Entidade::factory()->create();
        $secundaria = Entidade::factory()->empresaAplicacao()->create();

        $this->postJson("/api/entidades/{$principal->id}/agrupar-com/{$secundaria->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('entidades', ['id' => $secundaria->id]);
    });

    it('devolve 404 quando a principal não existe', function (): void {
        $secundaria = Entidade::factory()->create();

        $this->postJson("/api/entidades/00000000-0000-0000-0000-000000000000/agrupar-com/{$secundaria->id}")
            ->assertNotFound();
    });

    it('devolve 404 quando a secundária está soft-deleted', function (): void {
        $principal = Entidade::factory()->create();
        $secundaria = Entidade::factory()->create();
        $secundaria->delete();

        $this->postJson("/api/entidades/{$principal->id}/agrupar-com/{$secundaria->id}")
            ->assertNotFound();
    });
});

it('utilizador sem permissão recebe 403', function (): void {
    $principal = Entidade::factory()->create();
    $secundaria = Entidade::factory()->create();
    criarEAutenticarUtilizador();

    $this->postJson("/api/entidades/{$principal->id}/agrupar-com/{$secundaria->id}")
        ->assertForbidden();
});

it('guest sem token recebe 401', function (): void {
    $principal = Entidade::factory()->create();
    $secundaria = Entidade::factory()->create();

    $this->postJson("/api/entidades/{$principal->id}/agrupar-com/{$secundaria->id}")
        ->assertUnauthorized();
});
