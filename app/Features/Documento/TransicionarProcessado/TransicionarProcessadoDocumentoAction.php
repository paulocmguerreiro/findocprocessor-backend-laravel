<?php

declare(strict_types=1);

namespace App\Features\Documento\TransicionarProcessado;

use App\Events\DocumentoProcessado;
use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Features\Documento\Transicao\RegraNomearProcessado;
use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Transição `AnaliseIaLocal|AnaliseCloud → Processado` (pipeline). Preenche os
 * campos de domínio extraídos, move o ficheiro `enviado → processado` e
 * renomeia-o para o nome canónico; emite `DocumentoProcessado`.
 */
final readonly class TransicionarProcessadoDocumentoAction
{
    public function __construct(
        private ExecutorTransicaoDocumento $executor,
        private RegraNomearProcessado $nomear,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Documento $documento, TransicionarProcessadoDocumentoDto $dados): Documento
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
            'processamento concluído',
            camposDominio: [
                'id_fornecedor' => $dados->idFornecedor,
                'id_cliente' => $dados->idCliente,
                'id_categoria' => $dados->idCategoria,
                'valor' => $dados->valor,
                'data_documento' => $dados->dataDocumento,
            ],
            nomeDestino: $nomeCanonico,
            evento: fn (Documento $documentoProcessado): DocumentoProcessado => new DocumentoProcessado($documentoProcessado),
        );
    }
}
