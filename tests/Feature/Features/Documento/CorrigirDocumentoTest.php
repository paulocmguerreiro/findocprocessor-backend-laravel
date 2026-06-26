<?php

declare(strict_types=1);

use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('processado');
    criarEAutenticarAdmin();
});

it('corrige um documento Processado e devolve 200 com os campos actualizados', function (): void {
    $documento = Documento::factory()->processado()->create([
        'nome_ficheiro_original' => 'scan.pdf',
        'nome_ficheiro_storage' => 'antigo.pdf',
    ]);
    Storage::disk('processado')->put('antigo.pdf', 'conteudo');

    $fornecedor = Entidade::factory()->create(['nome' => 'Novo Fornecedor']);

    $this->patchJson("/api/documentos/{$documento->id}", [
        'id_fornecedor' => $fornecedor->id,
        'id_cliente' => Entidade::factory()->create()->id,
        'id_categoria' => CategoriaDocumento::factory()->create(['nome' => 'Nova Categoria'])->id,
        'valor' => 999,
        'data_documento' => '2026-06-25',
    ])
        ->assertOk()
        ->assertJsonPath('data.fornecedor.nome', 'Novo Fornecedor');

    Storage::disk('processado')->assertExists('2026-06-25-novo-fornecedor-nova-categoria.pdf');
    $this->assertDatabaseHas('documentos', ['id' => $documento->id, 'id_fornecedor' => $fornecedor->id]);
});

it('rejeita a correcção com fornecedor inexistente (422)', function (): void {
    $documento = Documento::factory()->processado()->create();

    $this->patchJson("/api/documentos/{$documento->id}", [
        'id_fornecedor' => '00000000-0000-0000-0000-000000000000',
        'id_cliente' => Entidade::factory()->create()->id,
        'id_categoria' => CategoriaDocumento::factory()->create()->id,
        'valor' => 10,
        'data_documento' => '2026-06-25',
    ])->assertUnprocessable();
});

it('utilizador sem permissão de escrita recebe 403', function (): void {
    $documento = Documento::factory()->processado()->create();

    criarEAutenticarUtilizador();

    $this->patchJson("/api/documentos/{$documento->id}", [
        'id_fornecedor' => Entidade::factory()->create()->id,
        'id_cliente' => Entidade::factory()->create()->id,
        'id_categoria' => CategoriaDocumento::factory()->create()->id,
        'valor' => 10,
        'data_documento' => '2026-06-25',
    ])->assertForbidden();
});
