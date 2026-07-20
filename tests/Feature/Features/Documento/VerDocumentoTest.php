<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
use App\Models\EtapaDocumento;
use App\Models\User;
use App\Shared\Enums\ResultadoEtapa;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn (): User => criarEAutenticarAdmin());

it('mostra o documento com fornecedor, cliente, categoria e histórico e devolve 200', function (): void {
    $fornecedor = Entidade::factory()->fornecedor()->create(['nome' => 'Fornecedor Ver']);
    $cliente = Entidade::factory()->cliente()->create(['nome' => 'Cliente Ver']);
    $categoria = CategoriaDocumento::factory()->create(['nome' => 'Categoria Ver']);
    $documento = Documento::factory()->processado()->create([
        'id_fornecedor' => $fornecedor->id,
        'id_cliente' => $cliente->id,
        'id_categoria' => $categoria->id,
    ]);
    EtapaDocumento::factory()->processado()->for($documento, 'documento')->create();

    $this->getJson("/api/documentos/{$documento->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $documento->id)
        ->assertJsonPath('data.fornecedor.nome', 'Fornecedor Ver')
        ->assertJsonPath('data.cliente.nome', 'Cliente Ver')
        ->assertJsonPath('data.categoria.nome', 'Categoria Ver')
        ->assertJsonCount(1, 'data.historico')
        ->assertJsonPath('data.historico.0.estado', 'PROCESSADO')
        ->assertJsonPath('data.historico.0.resultado', null);
});

it('mostra resultado preenchido numa linha de IA do histórico', function (): void {
    $documento = Documento::factory()->processado()->create();
    EtapaDocumento::factory()->passoIa(ResultadoEtapa::Sucesso)->for($documento, 'documento')->create();

    $this->getJson("/api/documentos/{$documento->id}")
        ->assertOk()
        ->assertJsonPath('data.historico.0.resultado', 'SUCESSO');
});

it('devolve 404 para um documento inexistente', function (): void {
    $this->getJson('/api/documentos/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});

it('utilizador com permissão de leitura vê e devolve 200', function (): void {
    $documento = Documento::factory()->processado()->create();

    criarEAutenticarUtilizador();

    $this->getJson("/api/documentos/{$documento->id}")->assertOk();
});

it('utilizador sem permissão de leitura recebe 403', function (): void {
    $documento = Documento::factory()->processado()->create();

    criarEAutenticarSemRole();

    $this->getJson("/api/documentos/{$documento->id}")->assertForbidden();
});
