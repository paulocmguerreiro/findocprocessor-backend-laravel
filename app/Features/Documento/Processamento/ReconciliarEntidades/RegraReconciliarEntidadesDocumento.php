<?php

declare(strict_types=1);

namespace App\Features\Documento\Processamento\ReconciliarEntidades;

use App\Infrastructure\AI\ResultadoExtracaoIA;
use App\Models\Entidade;
use App\Models\TipoDocumento;
use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Invariante de domínio (RF-10/RN-02, #111): a partir de um `ResultadoExtracaoIA`
 * completo e do `TipoDocumento`, resolve `id_fornecedor`/`id_cliente` **por lado**:
 *
 * 1. lado igual a `TipoDocumento.posicao_empresa_mae` → **empresa mãe**
 *    (`Entidade::whereEmpresaAplicacao()->firstOrFail()`, singleton), sem find-or-create;
 * 2. lado com `espera_<lado> = true` → **find-or-create** por `nif` exacto
 *    (`nome` + `nif` + flag `e_fornecedor`/`e_cliente`); entidade existente é reutilizada;
 * 3. lado com `espera_<lado> = false` e **não** empresa mãe → **`null`** (não cria).
 *
 * `id_categoria` vem sempre do `TipoDocumento`. Se não houver empresa mãe
 * configurada, `firstOrFail()` lança `ModelNotFoundException` — o orquestrador
 * encaminha o documento para `Erro` (RN-06, config operacional em falta).
 */
final readonly class RegraReconciliarEntidadesDocumento
{
    /**
     * @throws ModelNotFoundException
     */
    public function handle(ResultadoExtracaoIA $resultado, TipoDocumento $tipoDocumento): ResultadoReconciliacaoEntidades
    {
        $empresaMae = Entidade::query()->whereEmpresaAplicacao()->firstOrFail();
        $posicaoEmpresaMae = $tipoDocumento->posicao_empresa_mae;

        $idFornecedor = $this->resolverLado(
            $posicaoEmpresaMae === PosicaoEmpresaMae::Fornecedor,
            $tipoDocumento->espera_fornecedor,
            $resultado->nifFornecedor,
            $resultado->nomeFornecedor,
            'e_fornecedor',
            $empresaMae,
        );

        $idCliente = $this->resolverLado(
            $posicaoEmpresaMae === PosicaoEmpresaMae::Cliente,
            $tipoDocumento->espera_cliente,
            $resultado->nifCliente,
            $resultado->nomeCliente,
            'e_cliente',
            $empresaMae,
        );

        $nomeExtraido = $resultado->nomeFornecedor;
        $nomeFornecedorParaNome = $nomeExtraido !== null && trim($nomeExtraido) !== ''
            ? $nomeExtraido
            : $empresaMae->nome;

        return new ResultadoReconciliacaoEntidades(
            idFornecedor: $idFornecedor,
            idCliente: $idCliente,
            idCategoria: $tipoDocumento->id_categoria,
            nomeFornecedorParaNome: $nomeFornecedorParaNome,
        );
    }

    /**
     * @param  'e_fornecedor'|'e_cliente'  $flagPapel
     */
    private function resolverLado(
        bool $eEmpresaMae,
        bool $espera,
        ?string $nif,
        ?string $nome,
        string $flagPapel,
        Entidade $empresaMae,
    ): ?string {
        if ($eEmpresaMae) {
            return $empresaMae->id;
        }

        if (! $espera) {
            return null;
        }

        return Entidade::query()->firstOrCreate(
            ['nif' => (string) $nif],
            ['nome' => (string) $nome, $flagPapel => true],
        )->id;
    }
}
