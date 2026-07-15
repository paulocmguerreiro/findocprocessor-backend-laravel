<?php

declare(strict_types=1);

use App\Infrastructure\AI\CamadaIA;
use App\Infrastructure\AI\ClienteExtracaoIAPrism;
use App\Infrastructure\AI\PromptBuilder;
use App\Models\CategoriaDocumento;
use App\Models\Entidade;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Request as StructuredRequest;
use Prism\Prism\Testing\StructuredResponseFake;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Entidade::factory()->empresaAplicacao()->create();

    config([
        'extracao.local' => ['provider' => 'ollama', 'modelo' => 'llama3', 'url' => 'http://localhost:11434/v1', 'activa' => true],
        'extracao.cloud' => ['provider' => 'anthropic', 'modelo' => 'claude-opus-4-8', 'url' => null, 'key' => null, 'activa' => false],
    ]);
});

it('produz completo quando todos os espera_* estão presentes e válidos', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    $tipoDocumento = TipoDocumento::factory()->create([
        'nome' => 'Fatura',
        'id_categoria' => $categoria->id,
        'espera_data_documento' => true,
        'espera_fornecedor' => true,
        'espera_cliente' => true,
        'espera_valor' => true,
    ]);

    Prism::fake([
        StructuredResponseFake::make()->withStructured([
            'tipo_documento' => 'Fatura',
            'data_documento' => '2026-07-15',
            'fornecedor' => ['nif' => '123456789', 'nome' => 'Fornecedor Lda'],
            'cliente' => ['nif' => '987654321', 'nome' => 'Cliente Lda'],
            'valor' => 123.45,
        ]),
    ]);

    $resultado = (new ClienteExtracaoIAPrism)->extrair('texto extraído do documento', CamadaIA::Local);

    expect($resultado->ehCompleto())->toBeTrue()
        ->and($resultado->tipoDocumento?->id)->toBe($tipoDocumento->id)
        ->and($resultado->idCategoria)->toBe($categoria->id)
        ->and($resultado->dataDocumento?->format('Y-m-d'))->toBe('2026-07-15')
        ->and($resultado->nifFornecedor)->toBe('123456789')
        ->and($resultado->nomeFornecedor)->toBe('Fornecedor Lda')
        ->and($resultado->nifCliente)->toBe('987654321')
        ->and($resultado->nomeCliente)->toBe('Cliente Lda')
        ->and($resultado->valor)->toBe(123.45);
});

it('produz completo mesmo sem fornecedor quando o tipo não o espera', function (): void {
    TipoDocumento::factory()->create([
        'nome' => 'Recibo',
        'espera_data_documento' => false,
        'espera_fornecedor' => false,
        'espera_cliente' => false,
        'espera_valor' => false,
    ]);

    Prism::fake([
        StructuredResponseFake::make()->withStructured(['tipo_documento' => 'Recibo']),
    ]);

    $resultado = (new ClienteExtracaoIAPrism)->extrair('texto', CamadaIA::Local);

    expect($resultado->ehCompleto())->toBeTrue()
        ->and($resultado->dataDocumento)->toBeNull()
        ->and($resultado->nifFornecedor)->toBeNull()
        ->and($resultado->valor)->toBeNull();
});

it('produz incompleto quando falta um campo esperado', function (): void {
    TipoDocumento::factory()->create([
        'nome' => 'Fatura',
        'espera_data_documento' => false,
        'espera_fornecedor' => false,
        'espera_cliente' => false,
        'espera_valor' => true,
    ]);

    Prism::fake([
        StructuredResponseFake::make()->withStructured(['tipo_documento' => 'Fatura']),
    ]);

    $resultado = (new ClienteExtracaoIAPrism)->extrair('texto', CamadaIA::Local);

    expect($resultado->ehIncompleto())->toBeTrue()
        ->and($resultado->motivosFalta)->toBe(['valor em falta ou inválido.']);
});

it('produz incompleto quando o fornecedor esperado vem sem nome', function (): void {
    TipoDocumento::factory()->create([
        'nome' => 'Fatura',
        'espera_data_documento' => false,
        'espera_fornecedor' => true,
        'espera_cliente' => false,
        'espera_valor' => false,
    ]);

    Prism::fake([
        StructuredResponseFake::make()->withStructured([
            'tipo_documento' => 'Fatura',
            'fornecedor' => ['nif' => '123456789'],
        ]),
    ]);

    $resultado = (new ClienteExtracaoIAPrism)->extrair('texto', CamadaIA::Local);

    expect($resultado->ehIncompleto())->toBeTrue()
        ->and($resultado->motivosFalta)->toBe(['fornecedor (nif/nome) em falta ou inválido.']);
});

it('produz incompleto quando o NIF do fornecedor está fora do intervalo válido', function (): void {
    TipoDocumento::factory()->create([
        'nome' => 'Fatura',
        'espera_data_documento' => false,
        'espera_fornecedor' => true,
        'espera_cliente' => false,
        'espera_valor' => false,
    ]);

    Prism::fake([
        StructuredResponseFake::make()->withStructured([
            'tipo_documento' => 'Fatura',
            'fornecedor' => ['nif' => 'ab', 'nome' => 'Fornecedor Lda'],
        ]),
    ]);

    $resultado = (new ClienteExtracaoIAPrism)->extrair('texto', CamadaIA::Local);

    expect($resultado->ehIncompleto())->toBeTrue()
        ->and($resultado->motivosFalta)->toBe(['fornecedor (nif/nome) em falta ou inválido.']);
});

it('produz desconhecido quando o tipo_documento não corresponde a nenhum TipoDocumento', function (): void {
    Prism::fake([
        StructuredResponseFake::make()->withStructured(['tipo_documento' => 'inexistente']),
    ]);

    $resultado = (new ClienteExtracaoIAPrism)->extrair('texto', CamadaIA::Local);

    expect($resultado->ehDesconhecido())->toBeTrue()
        ->and($resultado->tipoDocumento)->toBeNull();
});

it('produz desconhecido quando o tipo_documento vem ausente da resposta', function (): void {
    Prism::fake([
        StructuredResponseFake::make()->withStructured([]),
    ]);

    $resultado = (new ClienteExtracaoIAPrism)->extrair('texto', CamadaIA::Local);

    expect($resultado->ehDesconhecido())->toBeTrue();
});

it('produz perigoso com precedência sobre outros campos preenchidos', function (): void {
    Prism::fake([
        StructuredResponseFake::make()->withStructured([
            'tipo_documento' => 'perigoso',
            'motivo' => 'conteúdo suspeito detectado',
            'valor' => 999,
        ]),
    ]);

    $resultado = (new ClienteExtracaoIAPrism)->extrair('texto', CamadaIA::Local);

    expect($resultado->ehPerigoso())->toBeTrue()
        ->and($resultado->motivo)->toBe('conteúdo suspeito detectado');
});

it('produz falhaTecnica quando o Prism não tem resposta disponível', function (): void {
    Prism::fake([
        StructuredResponseFake::make()->withStructured(['tipo_documento' => 'desconhecido']),
    ]);

    $cliente = new ClienteExtracaoIAPrism;
    $cliente->extrair('texto', CamadaIA::Local);

    $resultado = $cliente->extrair('texto', CamadaIA::Local);

    expect($resultado->estaEmFalhaTecnica())->toBeTrue()
        ->and($resultado->motivo)->not->toBeNull();
});

it('envia o nonce concreto e o system prompt do PromptBuilder no pedido', function (): void {
    TipoDocumento::factory()->create(['nome' => 'Fatura']);

    $fake = Prism::fake([
        StructuredResponseFake::make()->withStructured(['tipo_documento' => 'Fatura']),
    ]);

    (new ClienteExtracaoIAPrism)->extrair('texto sensível do documento', CamadaIA::Local);

    $systemPromptEsperado = PromptBuilder::novo()->comInstrucoesBase()->comEmpresaMae()->comTiposDocumento()->construir();

    $fake->assertRequest(function (array $pedidos) use ($systemPromptEsperado): void {
        expect($pedidos)->toHaveCount(1);

        /** @var StructuredRequest $pedido */
        $pedido = $pedidos[0];

        expect($pedido->systemPrompts()[0]->content)->toBe($systemPromptEsperado)
            ->and($pedido->prompt())->toContain('texto sensível do documento')
            ->and($pedido->prompt())->toMatch('#<([A-Za-z0-9]{32})>texto sensível do documento</\1>#');
    });
});

it('deriva idCategoria do TipoDocumento mesmo que o JSON traga uma categoria diferente', function (): void {
    $categoria = CategoriaDocumento::factory()->create();
    TipoDocumento::factory()->create([
        'nome' => 'Fatura',
        'id_categoria' => $categoria->id,
        'espera_data_documento' => false,
        'espera_fornecedor' => false,
        'espera_cliente' => false,
        'espera_valor' => false,
    ]);
    $outraCategoria = CategoriaDocumento::factory()->create();

    Prism::fake([
        StructuredResponseFake::make()->withStructured([
            'tipo_documento' => 'Fatura',
            'categoria' => $outraCategoria->id,
        ]),
    ]);

    $resultado = (new ClienteExtracaoIAPrism)->extrair('texto', CamadaIA::Local);

    expect($resultado->ehCompleto())->toBeTrue()
        ->and($resultado->idCategoria)->toBe($categoria->id)
        ->and($resultado->idCategoria)->not->toBe($outraCategoria->id);
});

it('passa provider/modelo/ligação da camada cloud ao Prism, incluindo a key', function (): void {
    config(['extracao.cloud' => [
        'provider' => 'anthropic',
        'modelo' => 'claude-opus-4-8',
        'url' => 'https://api.anthropic.com/v1',
        'key' => 'segredo',
        'activa' => true,
    ]]);

    TipoDocumento::factory()->create(['nome' => 'Fatura']);

    $fake = Prism::fake([
        StructuredResponseFake::make()->withStructured(['tipo_documento' => 'Fatura']),
    ]);

    (new ClienteExtracaoIAPrism)->extrair('texto', CamadaIA::Cloud);

    $fake->assertProviderConfig(['url' => 'https://api.anthropic.com/v1', 'api_key' => 'segredo']);
});
