<?php

declare(strict_types=1);

namespace App\Features\Documento\Corrigir;

use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Features\Documento\Transicao\RegraNomearProcessado;
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
        private ExecutorTransicaoDocumento $executor,
        private RegraNomearProcessado $nomear,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Documento $documento, CorrigirDocumentoDto $dados): Documento
    {
        Gate::authorize('update', $documento);

        $fornecedor = Entidade::findOrFail($dados->idFornecedor);
        $categoria = CategoriaDocumento::findOrFail($dados->idCategoria);

        $nomeCanonico = $this->nomear->handle(
            $dados->dataDocumento,
            $fornecedor->nome,
            $categoria->nome,
            $documento->nome_ficheiro_original,
        );

        return $this->executor->executar(
            $documento,
            EstadoDocumento::Processado,
            'correcção',
            camposDominio: [
                'id_fornecedor' => $dados->idFornecedor,
                'id_cliente' => $dados->idCliente,
                'id_categoria' => $dados->idCategoria,
                'valor' => $dados->valor,
                'data_documento' => $dados->dataDocumento,
            ],
            nomeDestino: $nomeCanonico,
        );
    }
}
