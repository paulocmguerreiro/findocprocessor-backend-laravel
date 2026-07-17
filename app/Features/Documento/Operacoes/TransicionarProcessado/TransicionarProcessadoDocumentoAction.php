<?php

declare(strict_types=1);

namespace App\Features\Documento\Operacoes\TransicionarProcessado;

use App\Events\DocumentoProcessadoEvent;
use App\Features\Documento\Operacoes\Transicao\ExecutorTransicaoDocumento;
use App\Features\Documento\Operacoes\Transicao\RegraNomearProcessado;
use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Transição `AnaliseIaLocal|AnaliseCloud → Processado` (pipeline). Preenche os
 * campos de domínio extraídos, move o ficheiro `enviado → processado` e
 * renomeia-o para o nome canónico; emite `DocumentoProcessadoEvent`.
 */
final readonly class TransicionarProcessadoDocumentoAction
{
    public function __construct(
        private ExecutorTransicaoDocumento $executorTransicao,
        private RegraNomearProcessado $regraNomear,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Documento $documento, TransicionarProcessadoDocumentoDto $dadosProcessamento): Documento
    {
        Gate::authorize('update', $documento);

        $nomeFornecedor = $dadosProcessamento->idFornecedor !== null
            ? Entidade::findOrFail($dadosProcessamento->idFornecedor)->nome
            : null;
        $categoria = CategoriaDocumento::findOrFail($dadosProcessamento->idCategoria);

        $nomeCanonico = $this->regraNomear->handle(
            $dadosProcessamento->dataDocumento,
            $nomeFornecedor,
            $dadosProcessamento->nomeFornecedorExtraido,
            $categoria->nome,
            $documento->nome_ficheiro_original,
            $documento->created_at,
        );

        return $this->executorTransicao->executar(
            $documento,
            EstadoDocumento::Processado,
            'processamento concluído',
            camposDominio: [
                'id_fornecedor' => $dadosProcessamento->idFornecedor,
                'id_cliente' => $dadosProcessamento->idCliente,
                'id_categoria' => $dadosProcessamento->idCategoria,
                'valor' => $dadosProcessamento->valor,
                'data_documento' => $dadosProcessamento->dataDocumento,
            ],
            nomeDestino: $nomeCanonico,
            evento: fn (Documento $documentoProcessado): DocumentoProcessadoEvent => new DocumentoProcessadoEvent($documentoProcessado),
        );
    }
}
