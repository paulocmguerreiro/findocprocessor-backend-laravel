<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

/**
 * Constrói o system prompt de extração. **Deliberadamente role-neutral**: nunca
 * menciona a empresa mãe nem diz, por tipo, se ela é fornecedor ou cliente — isso
 * enviesava o modelo (activava o prior "a nossa empresa é a compradora") antes de
 * ele ler o documento. O modelo lê emissor/destinatário do documento; a
 * correspondência com a empresa mãe (por NIF) e a resolução de papéis fazem-se em
 * código (`ClienteExtracaoIAPrism`/`RegraReconciliarEntidadesDocumento`).
 */
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
     * Instruções neutras de leitura: define emissor/destinatário por papel (quem
     * emite vs quem recebe), sem partir do princípio de posições fixas nem nomear
     * qualquer entidade. Inclui a legenda hOCR (usada só quando o texto traz blocos
     * com `bbox`) e a nota de formato numérico português.
     */
    public function comInstrucoesExtracao(): self
    {
        $this->segmentosAdicionais[] = <<<'TEXTO'
            COMO LER O DOCUMENTO (não uses conhecimento externo sobre as empresas — lê só o documento):
            - Toda a fatura tem um EMISSOR (quem a emite/produz — o vendedor/prestador) e um DESTINATÁRIO (a quem se dirige — o comprador/adquirente). Identifica cada um pelo NIF e nome.
            - Os dados de cada parte podem surgir em várias zonas (cabeçalho, à esquerda ou à direita, um abaixo do outro, por vezes no rodapé); reconhece o destinatário por marcadores como "Exmo(s). Senhor(es)", "Cliente", "Destinatário", "Adquirente" ou "Faturar a".
            - Se o texto contiver blocos <block bbox='x0 y0 x1 y1'>...</block>, as coordenadas dão a posição de cada bloco (x0=esquerda, y0=topo, x1=direita, y1=base); usa-as para reconstruir o layout. Caso contrário, trata o texto como linear.

            FORMATO NUMÉRICO: os valores estão em formato português — "." separa os milhares e "," é o separador decimal (ex.: 2.091,00 → 2091.00).
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
                '- %s (categoria: %s) — %s',
                $tipo->nome,
                $tipo->categoria === null ? 'sem-categoria' : $tipo->categoria->slug,
                $tipo->descricao,
            ))
            ->implode(PHP_EOL);

        $this->segmentosAdicionais[] = "Passo 1 — Classificação. Em \"tipo_documento\" devolve EXACTAMENTE o nome de um dos tipos abaixo (o texto antes do parêntesis), classificando pela NATUREZA do documento; nunca a categoria:\n{$classificacao}";

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
