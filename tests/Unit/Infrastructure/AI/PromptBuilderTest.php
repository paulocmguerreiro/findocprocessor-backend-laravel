<?php

declare(strict_types=1);

use App\Infrastructure\AI\PromptBuilder;
use App\Models\Entidade;
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
