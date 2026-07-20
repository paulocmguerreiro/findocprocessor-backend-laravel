<?php

declare(strict_types=1);

namespace App\Features\Documento\Processamento;

use App\Features\Documento\Atribuicao\ReivindicarDocumentoEmEtapaAction;
use App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoAction;
use App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoDto;
use App\Features\Documento\Processamento\ConcluirExtracao\ConcluirExtracaoDocumentoAction;
use App\Features\Documento\Processamento\MarcarAnaliseCloud\MarcarAnaliseCloudDocumentoAction;
use App\Infrastructure\AI\CamadaIA;
use App\Infrastructure\AI\ContratoClienteIA;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Enums\ResultadoEtapa;

/**
 * Orquestrador da etapa `AnaliseIaLocal` (RF-07/RF-09/RF-10/RF-11/RF-12): reclama
 * o próximo documento nesse estado e, se a camada local estiver activa, invoca o
 * modelo local (`ContratoClienteIA::extrair(..., CamadaIA::Local)`); encaminha o
 * veredicto:
 *
 * - **completo** → reconcilia entidades (RF-10) → `Processado` (RF-11);
 * - **desconhecido/incompleto** → escala para `AnaliseCloud`;
 * - **perigoso** → `Perigoso`;
 * - **falha técnica** → contabiliza tentativa (à `max_tentativas` → `Erro`).
 *
 * Guarda de camada (RN-04): se `config('extracao.local.activa')` for falsa, salta
 * para `AnaliseCloud` **sem** contar tentativa. Empresa mãe em falta na reconciliação
 * (RN-06) → `Erro` directo, sem contar tentativa. Acção de sistema — sem `Gate`.
 */
final readonly class ProcessarAnaliseIaLocalDocumentoAction
{
    public function __construct(
        private ReivindicarDocumentoEmEtapaAction $reivindicar,
        private ContratoClienteIA $clienteIA,
        private RegistarEtapaExtracaoAction $registarEtapa,
        private RegistarFalhaTecnicaExtracaoAction $registarFalhaTecnica,
        private ConcluirExtracaoDocumentoAction $concluirExtracao,
        private MarcarAnaliseCloudDocumentoAction $marcarAnaliseCloud,
        private MarcarPerigosoDocumentoAction $marcarPerigoso,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(): ?Documento
    {
        $documento = $this->reivindicar->handle(EstadoDocumento::AnaliseIaLocal);

        if (! $documento instanceof Documento) {
            return null;
        }

        if (! config()->boolean('extracao.local.activa')) {
            return $this->escalarParaCloud($documento, 'camada de IA local inactiva');
        }

        $resultado = $this->clienteIA->extrair($this->textoExtraido($documento), CamadaIA::Local);

        return match (true) {
            $resultado->ehCompleto() => $this->concluirExtracao->handle($documento, $resultado),
            $resultado->ehPerigoso() => $this->marcarPerigoso->handle(
                $documento,
                new MarcarPerigosoDocumentoDto($resultado->motivo ?? 'conteúdo perigoso detectado'),
            ),
            $resultado->estaEmFalhaTecnica() => $this->registarFalhaTecnica->handle(
                $documento,
                $resultado->motivo ?? 'falha técnica do modelo local',
            ),
            // desconhecido ou incompleto → o modelo local não concluiu; escala para cloud.
            default => $this->escalarParaCloud($documento, 'veredicto inconclusivo do modelo local'),
        };
    }

    /**
     * @throws \Throwable
     */
    private function escalarParaCloud(Documento $documento, string $motivo): Documento
    {
        // Preserva o texto extraído (a camada cloud precisa dele) e liberta o lease.
        $this->registarEtapa->handle($documento, new RegistarEtapaExtracaoDto(
            resultado: ResultadoEtapa::EmCurso,
            motivo: $motivo,
            textoExtraido: $this->textoExtraido($documento),
        ));

        return $this->marcarAnaliseCloud->handle($documento, $motivo);
    }

    private function textoExtraido(Documento $documento): string
    {
        $texto = ExtracaoDocumento::query()->where('id_documento', $documento->id)->value('texto_extraido');

        return is_string($texto) ? $texto : '';
    }
}
