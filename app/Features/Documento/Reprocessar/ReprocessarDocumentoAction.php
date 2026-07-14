<?php

declare(strict_types=1);

namespace App\Features\Documento\Reprocessar;

use App\Events\DocumentoReprocessado;
use App\Features\Documento\Transicao\ExecutorTransicaoDocumento;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Enums\EtapaExtracao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Transição `Erro → AguardaEnvio` (HTTP). Reabre um documento em erro para
 * reprocessamento, parametrizado pelo `modo`; move o ficheiro `erro → entrada`,
 * regista o `modo` como motivo e emite `DocumentoReprocessado`.
 *
 * Reset da dimensão de extracção (RN-03/RN-04, #94): abre a sua própria
 * `DB::transaction()`, dentro da qual a transição de estado
 * (`ExecutorTransicaoDocumento`) corre como transacção aninhada (`SAVEPOINT`).
 * Reseta `extracoes_documento` **só se existir linha** — usa `update()`, nunca
 * `create()`/`upsert()` — para não criar dimensão de extracção a documentos
 * que nunca lá entraram (ex.: erro de scan de malware em `Pendente`).
 */
final readonly class ReprocessarDocumentoAction
{
    public function __construct(private ExecutorTransicaoDocumento $executor) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Documento $documento, ReprocessarDocumentoDto $dados): Documento
    {
        Gate::authorize('update', $documento);

        return DB::transaction(function () use ($documento, $dados): Documento {
            $documentoReaberto = $this->executor->executar(
                $documento,
                EstadoDocumento::AguardaEnvio,
                $dados->modo->value,
                evento: fn (Documento $documentoReaberto): DocumentoReprocessado => new DocumentoReprocessado($documentoReaberto, $dados->modo),
            );

            ExtracaoDocumento::query()->where('id_documento', $documentoReaberto->id)->update([
                'etapa_extracao' => EtapaExtracao::Pendente,
                'extracao_reclamada_em' => null,
                'extracao_tentativas' => 0,
                'texto_extraido' => null,
                'dados_json' => null,
            ]);

            return $documentoReaberto;
        });
    }
}
