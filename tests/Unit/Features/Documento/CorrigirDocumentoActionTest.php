<?php

declare(strict_types=1);

use App\Events\DocumentoProcessado;
use App\Features\Documento\Corrigir\CorrigirDocumentoAction;
use App\Features\Documento\Corrigir\CorrigirDocumentoDto;
use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
use App\Models\User;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('processado');
    $this->actingAs(User::factory()->create());
});

it('corrige o domínio e renomeia o ficheiro quando o nome canónico muda', function (): void {
    $documento = Documento::factory()->processado()->create([
        'nome_ficheiro_original' => 'scan.pdf',
        'nome_ficheiro_storage' => 'antigo.pdf',
    ]);
    Storage::disk('processado')->put('antigo.pdf', 'conteudo');

    $dados = new CorrigirDocumentoDto(
        idFornecedor: Entidade::factory()->create(['nome' => 'Novo Fornecedor'])->id,
        idCliente: Entidade::factory()->create()->id,
        idCategoria: CategoriaDocumento::factory()->create(['nome' => 'Nova Categoria'])->id,
        valor: 999.0,
        dataDocumento: Carbon::parse('2026-06-25'),
    );

    Event::fake([DocumentoProcessado::class]);

    $resultado = app(CorrigirDocumentoAction::class)->handle($documento, $dados);

    expect($resultado->status)->toBe(EstadoDocumento::Processado)
        ->and($resultado->nome_ficheiro_storage)->toBe('2026-06-25-novo-fornecedor-nova-categoria.pdf')
        ->and($resultado->id_fornecedor)->toBe($dados->idFornecedor);

    Storage::disk('processado')->assertExists('2026-06-25-novo-fornecedor-nova-categoria.pdf');
    Storage::disk('processado')->assertMissing('antigo.pdf');
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Processado->value,
        'motivo' => 'correcção',
    ]);

    // A correcção não re-emite o evento de processamento.
    Event::assertNotDispatched(DocumentoProcessado::class);
});
