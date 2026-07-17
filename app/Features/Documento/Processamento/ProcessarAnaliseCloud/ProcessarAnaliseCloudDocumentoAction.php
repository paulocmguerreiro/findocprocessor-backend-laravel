<?php

declare(strict_types=1);

namespace App\Features\Documento\Processamento\ProcessarAnaliseCloud;

use App\Features\Documento\Atribuicao\ReivindicarDocumentoEmEtapa\ReivindicarDocumentoEmEtapaAction;
use App\Features\Documento\MarcarErro\MarcarErroDocumentoAction;
use App\Features\Documento\MarcarErro\MarcarErroDocumentoDto;
use App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoAction;
use App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoDto;
use App\Features\Documento\Processamento\ConcluirExtracao\ConcluirExtracaoDocumentoAction;
use App\Features\Documento\Processamento\RegistarFalhaTecnicaExtracao\RegistarFalhaTecnicaExtracaoAction;
use App\Infrastructure\AI\CamadaIA;
use App\Infrastructure\AI\ContratoClienteIA;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;

/**
 * Orquestrador da etapa `AnaliseCloud` (RF-07/RF-09) — a **última** camada de
 * análise: reclama o próximo documento nesse estado e, se a camada cloud estiver
 * activa, invoca o modelo cloud (`ContratoClienteIA::extrair(..., CamadaIA::Cloud)`);
 * encaminha o veredicto:
 *
 * - **completo** → reconcilia entidades → `Processado`;
 * - **perigoso** → `Perigoso`;
 * - **desconhecido/incompleto** → `Erro` (não há camada seguinte para escalar);
 * - **falha técnica** → contabiliza tentativa (à `max_tentativas` → `Erro`).
 *
 * Guarda de camada: se `config('extracao.cloud.activa')` for falsa → `Erro`
 * (`sem LLM cloud disponível`), sem contar tentativa. Todas as saídas (menos a
 * falha técnica retentável) são terminais. Acção de sistema — sem `Gate`.
 */
final readonly class ProcessarAnaliseCloudDocumentoAction
{
    public function __construct(
        private ReivindicarDocumentoEmEtapaAction $reivindicar,
        private ContratoClienteIA $clienteIA,
        private RegistarFalhaTecnicaExtracaoAction $registarFalhaTecnica,
        private ConcluirExtracaoDocumentoAction $concluirExtracao,
        private MarcarPerigosoDocumentoAction $marcarPerigoso,
        private MarcarErroDocumentoAction $marcarErro,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(): ?Documento
    {
        $documento = $this->reivindicar->handle(EstadoDocumento::AnaliseCloud);

        if (! $documento instanceof Documento) {
            return null;
        }

        if (! config()->boolean('extracao.cloud.activa')) {
            return $this->marcarErro->handle($documento, new MarcarErroDocumentoDto('sem LLM cloud disponível'));
        }

        $resultado = $this->clienteIA->extrair($this->textoExtraido($documento), CamadaIA::Cloud);

        return match (true) {
            $resultado->ehCompleto() => $this->concluirExtracao->handle($documento, $resultado),
            $resultado->ehPerigoso() => $this->marcarPerigoso->handle(
                $documento,
                new MarcarPerigosoDocumentoDto($resultado->motivo ?? 'conteúdo perigoso detectado'),
            ),
            $resultado->estaEmFalhaTecnica() => $this->registarFalhaTecnica->handle(
                $documento,
                $resultado->motivo ?? 'falha técnica do modelo cloud',
            ),
            // desconhecido ou incompleto → o cloud é a última camada; sem escalar → Erro.
            default => $this->marcarErro->handle(
                $documento,
                new MarcarErroDocumentoDto('modelo cloud não conseguiu extrair (veredicto inconclusivo)'),
            ),
        };
    }

    private function textoExtraido(Documento $documento): string
    {
        $texto = ExtracaoDocumento::query()->where('id_documento', $documento->id)->value('texto_extraido');

        return is_string($texto) ? $texto : '';
    }
}
