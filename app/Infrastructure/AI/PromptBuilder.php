<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Models\Entidade;

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
     * @throws \RuntimeException
     */
    public function comEmpresaMae(): self
    {
        $empresaMae = Entidade::whereEmpresaAplicacao()->first();

        if ($empresaMae === null) {
            throw new \RuntimeException('Nenhuma Entidade está marcada como empresa aplicação (e_empresa_aplicacao).');
        }

        $this->segmentosAdicionais[] = <<<TEXTO
            EMPRESA MÃE:
            Nome: {$empresaMae->nome}
            NIF: {$empresaMae->nif}

            Esta é a empresa mãe da aplicação. Usa este nome e NIF para determinares, para cada documento, se a empresa mãe surge como cliente ou como fornecedor na transacção — não inventes nem assumas outro NIF como sendo o da empresa mãe.
            TEXTO;

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
