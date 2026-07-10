<?php

declare(strict_types=1);

use App\Infrastructure\AI\PromptBuilder;
use App\Models\CategoriaDocumento;
use App\Models\Entidade;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('novo()', function (): void {
    it('devolve uma instância própria (fluente)', function (): void {
        expect(PromptBuilder::novo())->toBeInstanceOf(PromptBuilder::class);
    });
});

describe('construir()', function (): void {
    it('lança LogicException se comInstrucoesBase() nunca foi chamado', function (): void {
        expect(fn (): string => PromptBuilder::novo()->construir())
            ->toThrow(LogicException::class, 'comInstrucoesBase() tem de ser chamado antes de construir().');
    });

    it('devolve o conteúdo de base_instructions.txt quando comInstrucoesBase() é chamado', function (): void {
        $esperado = trim((string) file_get_contents(app_path('Shared/Prompts/base_instructions.txt')));

        $prompt = PromptBuilder::novo()->comInstrucoesBase()->construir();

        expect($prompt)->toBe($esperado);
    });
});

describe('comEmpresaMae()', function (): void {
    it('injecta nome e NIF da empresa aplicação no output', function (): void {
        $empresaMae = Entidade::factory()->empresaAplicacao()->create();

        $prompt = PromptBuilder::novo()->comInstrucoesBase()->comEmpresaMae()->construir();

        expect($prompt)->toContain($empresaMae->nome)
            ->toContain($empresaMae->nif);
    });

    it('lança RuntimeException se não existir Entidade empresa aplicação', function (): void {
        expect(fn (): PromptBuilder => PromptBuilder::novo()->comInstrucoesBase()->comEmpresaMae())
            ->toThrow(RuntimeException::class, 'Nenhuma Entidade está marcada como empresa aplicação (e_empresa_aplicacao).');
    });

    it('produz sempre instrucoesBase primeiro, independentemente da ordem de chamada', function (): void {
        Entidade::factory()->empresaAplicacao()->create();

        $instrucoesBase = trim((string) file_get_contents(app_path('Shared/Prompts/base_instructions.txt')));

        $promptOrdemNormal = PromptBuilder::novo()->comInstrucoesBase()->comEmpresaMae()->construir();
        $promptOrdemInvertida = PromptBuilder::novo()->comEmpresaMae()->comInstrucoesBase()->construir();

        expect(str_starts_with($promptOrdemNormal, $instrucoesBase))->toBeTrue()
            ->and($promptOrdemInvertida)->toBe($promptOrdemNormal);
    });
});

describe('comTiposDocumento()', function (): void {
    it('sem filtro inclui todos os TipoDocumento (Passo 1 + Passo 2)', function (): void {
        $categoria = CategoriaDocumento::factory()->create(['slug' => 'receitas']);
        $tipo = TipoDocumento::factory()->create([
            'nome' => 'Fatura Simples',
            'descricao' => 'Fatura emitida a um cliente',
            'id_categoria' => $categoria->id,
            'espera_data_documento' => true,
            'espera_fornecedor' => false,
            'espera_cliente' => true,
            'espera_valor' => true,
        ]);

        $prompt = PromptBuilder::novo()->comInstrucoesBase()->comTiposDocumento()->construir();

        expect($prompt)->toContain('Passo 1 — Classificação')
            ->toContain('Passo 2 — Campos a extrair por tipo')
            ->toContain('"receitas" → Fatura Simples: Fatura emitida a um cliente')
            ->toContain('- Fatura Simples: data_documento, cliente, valor')
            ->not->toContain('- Fatura Simples: data_documento, fornecedor, cliente, valor');

        expect($prompt)->toContain($tipo->nome);
    });

    it('com filtrarPorCategoria() chamado antes filtra correctamente', function (): void {
        $categoriaAlvo = CategoriaDocumento::factory()->create();
        $outraCategoria = CategoriaDocumento::factory()->create();
        $tipoAlvo = TipoDocumento::factory()->create(['id_categoria' => $categoriaAlvo->id]);
        $tipoOutro = TipoDocumento::factory()->create(['id_categoria' => $outraCategoria->id]);

        $prompt = PromptBuilder::novo()
            ->comInstrucoesBase()
            ->filtrarPorCategoria($categoriaAlvo)
            ->comTiposDocumento()
            ->construir();

        expect($prompt)->toContain($tipoAlvo->nome)
            ->not->toContain($tipoOutro->nome);
    });

    it('com filtrarPorCategoria() chamado depois não filtra retroactivamente (CA-11/RN-03)', function (): void {
        $categoriaAlvo = CategoriaDocumento::factory()->create();
        $outraCategoria = CategoriaDocumento::factory()->create();
        $tipoAlvo = TipoDocumento::factory()->create(['id_categoria' => $categoriaAlvo->id]);
        $tipoOutro = TipoDocumento::factory()->create(['id_categoria' => $outraCategoria->id]);

        $prompt = PromptBuilder::novo()
            ->comInstrucoesBase()
            ->comTiposDocumento()
            ->filtrarPorCategoria($categoriaAlvo)
            ->construir();

        expect($prompt)->toContain($tipoAlvo->nome)
            ->toContain($tipoOutro->nome);
    });

    it('aceita filtrarPorCategoria() com id em string', function (): void {
        $categoriaAlvo = CategoriaDocumento::factory()->create();
        $outraCategoria = CategoriaDocumento::factory()->create();
        $tipoAlvo = TipoDocumento::factory()->create(['id_categoria' => $categoriaAlvo->id]);
        $tipoOutro = TipoDocumento::factory()->create(['id_categoria' => $outraCategoria->id]);

        $prompt = PromptBuilder::novo()
            ->comInstrucoesBase()
            ->filtrarPorCategoria($categoriaAlvo->id)
            ->comTiposDocumento()
            ->construir();

        expect($prompt)->toContain($tipoAlvo->nome)
            ->not->toContain($tipoOutro->nome);
    });
});
