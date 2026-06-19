<?php

declare(strict_types=1);

use App\Features\Entidade\Ver\VerEntidadeAction;
use App\Models\Entidade;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

it('resolve entidade a partir de UUID string directamente na action', function (): void {
    $entidade = Entidade::factory()->create();

    $resultado = app(VerEntidadeAction::class)->handle($entidade->id);

    expect($resultado->id)->toBe($entidade->id);
});
