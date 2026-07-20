<?php

declare(strict_types=1);

use App\Infrastructure\Malware\ContratoAnalisadorMalware;
use App\Infrastructure\Malware\ResultadoAnaliseMalware;
use App\Models\CategoriaDocumento;
use App\Models\Entidade;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('processado');
    $this->utilizador = criarEAutenticarAdmin();
});

function payloadManual(array $sobrepor = []): array
{
    return array_merge([
        'id_fornecedor' => Entidade::factory()->create(['nome' => 'Fornecedor Lda'])->id,
        'id_cliente' => Entidade::factory()->create()->id,
        'id_categoria' => CategoriaDocumento::factory()->create(['nome' => 'Despesas'])->id,
        'valor' => 123.45,
        'data_documento' => '2026-06-25',
        'ficheiro' => UploadedFile::fake()->create('fatura.pdf', 100, 'application/pdf'),
    ], $sobrepor);
}

it('regista um documento manual e devolve 201 em Processado', function (): void {
    $cliente = Entidade::factory()->create(['nome' => 'Cliente Lda']);

    $this->post('/api/documentos', payloadManual(['id_cliente' => $cliente->id]))
        ->assertCreated()
        ->assertJsonPath('data.estado', EstadoDocumento::Processado->value)
        ->assertJsonPath('data.fornecedor.nome', 'Fornecedor Lda')
        ->assertJsonPath('data.cliente.nome', 'Cliente Lda')
        ->assertJsonPath('data.categoria.nome', 'Despesas')
        ->assertJsonPath('data.id_responsavel', $this->utilizador->id);

    Storage::disk('processado')->assertExists('2026-06-25-fornecedor-lda-despesas.pdf');
    $this->assertDatabaseCount('documentos', 1);
    $this->assertDatabaseHas('documentos', ['id_responsavel' => $this->utilizador->id]);
});

it('regista um documento infectado em Perigoso (disco perigoso), sempre persistido', function (): void {
    Storage::fake('perigoso');

    app()->instance(ContratoAnalisadorMalware::class, Mockery::mock(ContratoAnalisadorMalware::class, function ($mock): void {
        $mock->shouldReceive('analisar')->once()->andReturn(ResultadoAnaliseMalware::infectado('Eicar-Signature'));
    }));

    $this->post('/api/documentos', payloadManual())
        ->assertCreated()
        ->assertJsonPath('data.estado', EstadoDocumento::Perigoso->value);

    Storage::disk('perigoso')->assertExists('2026-06-25-fornecedor-lda-despesas.pdf');
    $this->assertDatabaseCount('documentos', 1);
});

it('rejeita o registo sem ficheiro com 422', function (): void {
    $this->postJson('/api/documentos', [
        'id_fornecedor' => Entidade::factory()->create()->id,
        'id_cliente' => Entidade::factory()->create()->id,
        'id_categoria' => CategoriaDocumento::factory()->create()->id,
        'valor' => 10,
        'data_documento' => '2026-06-25',
    ])->assertUnprocessable();
});

it('utilizador sem permissão de escrita recebe 403', function (): void {
    criarEAutenticarUtilizador();

    $this->post('/api/documentos', payloadManual())->assertForbidden();

    $this->assertDatabaseCount('documentos', 0);
});
