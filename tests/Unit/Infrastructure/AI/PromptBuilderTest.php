<?php

declare(strict_types=1);

use App\Infrastructure\AI\PromptBuilder;

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
