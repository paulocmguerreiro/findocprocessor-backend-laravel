<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Models\CategoriaDocumento;
use App\Models\Entidade;
use App\Models\TipoDocumento;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

final class PromptBuilder
{
    private ?string $instrucoesBase = null;

    /** @var list<string> */
    private array $segmentosAdicionais = [];

    private ?string $filtroCategoria = null;

    public static function novo(): self
    {
        return new self;
    }

    /**
     * @throws \RuntimeException
     */
    public function comInstrucoesBase(): self
    {
        try {
            $this->instrucoesBase = File::get(app_path('Shared/Prompts/base_instructions.txt'));
        } catch (FileNotFoundException $excepcao) {
            throw new \RuntimeException('Não foi possível ler app/Shared/Prompts/base_instructions.txt.', $excepcao->getCode(), previous: $excepcao);
        }

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

            Esta é a empresa mãe da aplicação. A posição em que este NIF surge em cada documento (como cliente ou como fornecedor) está indicada por tipo de documento na secção "Passo 1 — Classificação" ("empresa mãe: fornecedor" ou "empresa mãe: cliente") — não inventes nem assumas outro NIF como sendo o da empresa mãe.
            TEXTO;

        return $this;
    }

    public function filtrarPorCategoria(CategoriaDocumento|string $idCategoria): self
    {
        $this->filtroCategoria = $idCategoria instanceof CategoriaDocumento ? $idCategoria->id : $idCategoria;

        return $this;
    }

    public function comTiposDocumento(): self
    {
        $tiposDocumento = TipoDocumento::query()
            ->with('categoria')
            ->when($this->filtroCategoria, fn (Builder $query, string $idCategoria): Builder => $query->where('id_categoria', $idCategoria))
            ->get();

        $classificacao = $tiposDocumento
            ->map(fn (TipoDocumento $tipo): string => sprintf(
                '- "%s" → %s: %s (empresa mãe: %s)',
                $tipo->categoria === null ? 'sem-categoria' : $tipo->categoria->slug,
                $tipo->nome,
                $tipo->descricao,
                $tipo->posicao_empresa_mae->value,
            ))
            ->implode(PHP_EOL);

        $camposPorTipo = $tiposDocumento
            ->map(function (TipoDocumento $tipo): string {
                $campos = array_filter([
                    'data_documento' => $tipo->espera_data_documento,
                    'fornecedor' => $tipo->espera_fornecedor,
                    'cliente' => $tipo->espera_cliente,
                    'valor' => $tipo->espera_valor,
                ]);

                return sprintf('- %s: %s', $tipo->nome, implode(', ', array_keys($campos)));
            })
            ->implode(PHP_EOL);

        $this->segmentosAdicionais[] = "Passo 1 — Classificação:\n{$classificacao}";
        $this->segmentosAdicionais[] = "Passo 2 — Campos a extrair por tipo:\n{$camposPorTipo}";

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
