<?php

declare(strict_types=1);

namespace App\Features\Documento\Processamento\ConcluirExtracao;

use App\Infrastructure\AI\ResultadoExtracaoIA;
use App\Models\Entidade;
use App\Models\TipoDocumento;
use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Invariante de domínio (RF-10/RN-02): resolve `id_fornecedor`/`id_cliente` a partir
 * do emissor (=fornecedor) e destinatário (=cliente) extraídos, situando a empresa
 * mãe por **correspondência de NIF** (não por posição sugerida ao modelo — ver
 * `PromptBuilder`, extração role-neutral):
 *
 * 1. carrega a empresa mãe (`Entidade::whereEmpresaAplicacao()->firstOrFail()`, singleton);
 * 2. o lado (fornecedor/cliente) cujo NIF coincide com o da empresa mãe → **empresa mãe**
 *    (sem find-or-create); o outro lado → **find-or-create** por `nif` exacto;
 * 3. se o NIF da mãe não coincidir com nenhum dos lados, o documento não a envolve
 *    → `ModelNotFoundException` (o orquestrador encaminha para `Erro`, RN-06).
 *
 * O NIF é a chave (o nome pode abreviar/variar; o NIF ou está completo ou não serve).
 * `id_categoria` vem sempre do `TipoDocumento`.
 */
final readonly class RegraReconciliarEntidadesDocumento
{
    /**
     * @throws ModelNotFoundException
     */
    public function handle(ResultadoExtracaoIA $resultado, TipoDocumento $tipoDocumento): ResultadoReconciliacaoEntidades
    {
        $empresaMae = Entidade::query()->whereEmpresaAplicacao()->firstOrFail();

        $nifMae = $this->normalizarNif($empresaMae->nif);
        $fornecedorEhMae = $nifMae !== '' && $this->normalizarNif((string) $resultado->nifFornecedor) === $nifMae;
        $clienteEhMae = $nifMae !== '' && $this->normalizarNif((string) $resultado->nifCliente) === $nifMae;

        if (! $fornecedorEhMae && ! $clienteEhMae) {
            throw new ModelNotFoundException('Empresa mãe não identificada no documento: o NIF não corresponde ao emissor nem ao destinatário.');
        }

        $idFornecedor = $fornecedorEhMae
            ? $empresaMae->id
            : $this->encontrarOuCriar($resultado->nifFornecedor, $resultado->nomeFornecedor, 'e_fornecedor');

        $idCliente = $clienteEhMae
            ? $empresaMae->id
            : $this->encontrarOuCriar($resultado->nifCliente, $resultado->nomeCliente, 'e_cliente');

        // A direcção da empresa mãe (por NIF) é a fonte de verdade da categoria: se o
        // tipo classificado pelo LLM tiver a posição contrária (ex.: uma venda de
        // serviços emitida pela mãe classificada como "Fatura de Serviços"), corrige
        // para o tipo com a posição correcta — o LLM classifica a natureza, o NIF decide
        // o sentido (compra vs venda). Ver [[project_extracao_role_neutral_sem_vies]].
        $direccaoMae = $fornecedorEhMae ? PosicaoEmpresaMae::Fornecedor : PosicaoEmpresaMae::Cliente;
        $tipoResolvido = $this->tipoParaDireccao($tipoDocumento, $direccaoMae);

        $nomeExtraido = $resultado->nomeFornecedor;
        $nomeFornecedorParaNome = $nomeExtraido !== null && trim($nomeExtraido) !== ''
            ? $nomeExtraido
            : $empresaMae->nome;

        return new ResultadoReconciliacaoEntidades(
            idFornecedor: $idFornecedor,
            idCliente: $idCliente,
            idCategoria: $tipoResolvido->id_categoria,
            nomeFornecedorParaNome: $nomeFornecedorParaNome,
        );
    }

    /**
     * Devolve um `TipoDocumento` cuja `posicao_empresa_mae` corresponde à direcção
     * resolvida por NIF. Se o tipo classificado já corresponder, mantém-no; caso
     * contrário selecciona o único tipo com a posição correcta (quando há exactamente
     * um) — senão mantém o classificado (desambiguação por natureza fica para a IA).
     */
    private function tipoParaDireccao(TipoDocumento $tipoClassificado, PosicaoEmpresaMae $direccao): TipoDocumento
    {
        if ($tipoClassificado->posicao_empresa_mae === $direccao) {
            return $tipoClassificado;
        }

        $candidatos = TipoDocumento::query()->where('posicao_empresa_mae', $direccao->value)->get();
        $unico = $candidatos->first();

        return $candidatos->count() === 1 && $unico instanceof TipoDocumento ? $unico : $tipoClassificado;
    }

    /**
     * Find-or-create do lado que não é a empresa mãe, pelo NIF exacto. Sem NIF
     * (lado não esperado, ex.: extrato sem contraparte) devolve `null` — nunca cria
     * uma entidade órfã com NIF vazio.
     *
     * @param  'e_fornecedor'|'e_cliente'  $flagPapel
     */
    private function encontrarOuCriar(?string $nif, ?string $nome, string $flagPapel): ?string
    {
        $nifNormalizado = $nif === null ? '' : $this->normalizarNif($nif);

        if ($nifNormalizado === '') {
            return null;
        }

        return Entidade::query()->firstOrCreate(
            ['nif' => $nifNormalizado],
            ['nome' => (string) $nome, $flagPapel => true],
        )->id;
    }

    private function normalizarNif(string $nif): string
    {
        return str_replace(' ', '', trim($nif));
    }
}
