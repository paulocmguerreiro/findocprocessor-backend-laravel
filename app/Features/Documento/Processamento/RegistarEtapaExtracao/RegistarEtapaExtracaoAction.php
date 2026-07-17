<?php

declare(strict_types=1);

namespace App\Features\Documento\Processamento\RegistarEtapaExtracao;

use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Support\Facades\DB;

/**
 * Recorder do pipeline de extracção — regista o passo de IA numa única
 * transacção: upsert em `extracoes_documento` (dimensão de extracção) +
 * `EtapaDocumento` (histórico, `resultado` preenchido, `estado` igual ao
 * `estado` actual do `Documento` — que é já o passo em curso).
 *
 * Contrato "substituição total": cada chamada substitui inteiramente
 * `texto_extraido`/`dados_json` — o chamador (futuro orquestrador, #97/#98)
 * é responsável por enviar sempre o valor completo pretendido, nunca deltas.
 *
 * Acção de sistema (RNF-02): sem `Gate::authorize`, `EtapaDocumento` gravada
 * com `id_utilizador = null` (mesmo padrão das transições `Marcar*`,
 * `02-shared/padroes-acoes.md`).
 */
final readonly class RegistarEtapaExtracaoAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento, RegistarEtapaExtracaoDto $dados): ExtracaoDocumento
    {
        return DB::transaction(function () use ($documento, $dados): ExtracaoDocumento {
            $extracao = ExtracaoDocumento::query()->updateOrCreate(
                ['id_documento' => $documento->id],
                [
                    'extracao_reclamada_em' => $dados->reclamar ? now() : null,
                    'texto_extraido' => $dados->textoExtraido,
                    'dados_json' => $dados->dadosJson,
                ],
            );

            if ($dados->incrementarTentativas) {
                $extracao->increment('extracao_tentativas');
            }

            $documento->historico()->create([
                'estado' => $documento->estado,
                'resultado' => $dados->resultado,
                'motivo' => $dados->motivo,
                'id_utilizador' => null,
            ]);

            $this->cache->invalidarCache(TagCache::Documentos);

            return $extracao->refresh();
        });
    }
}
