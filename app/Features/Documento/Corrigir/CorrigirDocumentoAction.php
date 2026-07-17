<?php

declare(strict_types=1);

namespace App\Features\Documento\Corrigir;

use App\Features\Documento\Operacoes\Transicao\ExecutorTransicaoDocumento;
use App\Features\Documento\Operacoes\Transicao\RegraNomearProcessado;
use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Correcção de um Documento `Processado` (self-loop `Processado → Processado`).
 * Actualiza os campos de domínio e, se o nome canónico mudar (fornecedor/categoria/
 * data), renomeia o ficheiro no disco `processado`. Não emite evento.
 */
final readonly class CorrigirDocumentoAction
{
    public function __construct(
        private ExecutorTransicaoDocumento $executorTransicao,
        private RegraNomearProcessado $regraNomear,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Documento $documento, CorrigirDocumentoDto $dadosCorrecao): Documento
    {
        Gate::authorize('update', $documento);

        $fornecedor = Entidade::findOrFail($dadosCorrecao->idFornecedor);
        $categoria = CategoriaDocumento::findOrFail($dadosCorrecao->idCategoria);

        $nomeCanonico = $this->regraNomear->handle(
            $dadosCorrecao->dataDocumento,
            $fornecedor->nome,
            null,
            $categoria->nome,
            $documento->nome_ficheiro_original,
            $documento->created_at,
        );

        return $this->executorTransicao->executar(
            $documento,
            EstadoDocumento::Processado,
            'correcção',
            camposDominio: [
                'id_fornecedor' => $dadosCorrecao->idFornecedor,
                'id_cliente' => $dadosCorrecao->idCliente,
                'id_categoria' => $dadosCorrecao->idCategoria,
                'valor' => $dadosCorrecao->valor,
                'data_documento' => $dadosCorrecao->dataDocumento,
            ],
            nomeDestino: $nomeCanonico,
        );
    }
}
