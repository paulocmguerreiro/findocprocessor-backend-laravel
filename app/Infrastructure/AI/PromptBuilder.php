<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

final class PromptBuilder
{
    private ?string $instrucoesBase = null;

    /** @var list<string> */
    private array $segmentosAdicionais = [];

    public static function novo(): self
    {
        return new self;
    }

    /**
     * @throws \RuntimeException
     */
    public function comInstrucoesBase(): self
    {
        $conteudo = file_get_contents(app_path('Shared/Prompts/base_instructions.txt'));

        if ($conteudo === false) {
            throw new \RuntimeException('Não foi possível ler app/Shared/Prompts/base_instructions.txt.');
        }

        $this->instrucoesBase = $conteudo;

        return $this;
    }

    /**
     * @throws \LogicException
     */
    public function construir(): string
    {
        if ($this->instrucoesBase === null) {
            throw new \LogicException('comInstrucoesBase() tem de ser chamado antes de construir().');
        }

        return trim($this->instrucoesBase.PHP_EOL.PHP_EOL.implode(PHP_EOL.PHP_EOL, $this->segmentosAdicionais));
    }
}
