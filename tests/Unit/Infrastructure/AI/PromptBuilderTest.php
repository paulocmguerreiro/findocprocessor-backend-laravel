<?php

declare(strict_types=1);

use App\Infrastructure\AI\PromptBuilder;
use App\Models\CategoriaDocumento;
use App\Models\Entidade;
use App\Models\TipoDocumento;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

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

    it('lança RuntimeException se a leitura de base_instructions.txt falhar', function (): void {
        File::shouldReceive('get')
            ->once()
            ->andThrow(new FileNotFoundException);

        expect(fn (): PromptBuilder => PromptBuilder::novo()->comInstrucoesBase())
            ->toThrow(RuntimeException::class, 'Não foi possível ler app/Shared/Prompts/base_instructions.txt.');
    });
});

describe('comInstrucoesExtracao()', function (): void {
    it('injecta instruções de leitura role-neutral (emissor/destinatário) sem nomear entidades', function (): void {
        $empresaMae = Entidade::factory()->empresaAplicacao()->create();

        $prompt = PromptBuilder::novo()->comInstrucoesBase()->comInstrucoesExtracao()->construir();

        expect($prompt)->toContain('EMISSOR')
            ->toContain('DESTINATÁRIO')
            ->toContain('bbox')
            ->toContain('FORMATO NUMÉRICO')
            ->not->toContain($empresaMae->nome)
            ->not->toContain($empresaMae->nif);
    });

    it('não depende de existir empresa aplicação (extração role-neutral, não toca na BD)', function (): void {
        $prompt = PromptBuilder::novo()->comInstrucoesBase()->comInstrucoesExtracao()->construir();

        expect($prompt)->toContain('EMISSOR');
    });

    it('produz sempre instrucoesBase primeiro, independentemente da ordem de chamada', function (): void {
        $instrucoesBase = trim((string) file_get_contents(app_path('Shared/Prompts/base_instructions.txt')));

        $promptOrdemNormal = PromptBuilder::novo()->comInstrucoesBase()->comInstrucoesExtracao()->construir();
        $promptOrdemInvertida = PromptBuilder::novo()->comInstrucoesExtracao()->comInstrucoesBase()->construir();

        expect(str_starts_with($promptOrdemNormal, $instrucoesBase))->toBeTrue()
            ->and($promptOrdemInvertida)->toBe($promptOrdemNormal);
    });
});

describe('comTiposDocumento()', function (): void {
    it('sem filtro inclui todos os TipoDocumento (Passo 1, formato "nome (categoria) — descrição", sem enviesar o papel)', function (): void {
        $categoria = CategoriaDocumento::factory()->create(['slug' => 'receitas']);
        $tipo = TipoDocumento::factory()->create([
            'nome' => 'Fatura Simples',
            'descricao' => 'Fatura emitida a um cliente',
            'id_categoria' => $categoria->id,
        ]);

        $prompt = PromptBuilder::novo()->comInstrucoesBase()->comTiposDocumento()->construir();

        expect($prompt)->toContain('Passo 1 — Classificação')
            ->toContain('- Fatura Simples (categoria: receitas) — Fatura emitida a um cliente')
            ->not->toContain('empresa mãe')
            ->not->toContain('Passo 2');

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
