<?php

declare(strict_types=1);

namespace App\Features\Documento\Processamento;

use App\Features\Documento\Atribuicao\ReivindicarDocumentoEmEtapaAction;
use App\Features\Documento\Processamento\MarcarAnaliseIaLocal\MarcarAnaliseIaLocalDocumentoAction;
use App\Features\Documento\Processamento\MarcarAnaliseOcr\MarcarAnaliseOcrDocumentoAction;
use App\Infrastructure\Extracao\ExtractorTextoNativo;
use App\Infrastructure\Extracao\FalhaExtracaoTextoException;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Enums\ResultadoEtapa;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orquestrador da etapa `AnaliseTexto` (RF-05/RF-08/RF-12): reclama por lease o
 * próximo documento nesse estado e decide o caminho:
 *
 * - ficheiro **não-PDF** (imagem) → salta o parser nativo e encaminha para `AnaliseOcr`;
 * - PDF → `ExtractorTextoNativo::extrair()`: acima do threshold → `AnaliseIaLocal`,
 *   abaixo → `AnaliseOcr`.
 *
 * Cada passo é registado por `RegistarEtapaExtracaoAction` (que também **liberta o
 * lease** — `reclamar: false` — para a etapa seguinte poder reclamar já). Uma falha
 * técnica (`FalhaExtracaoTextoException`) conta uma tentativa; à `max_tentativas` o
 * documento vai a `Erro`. Acção de sistema — sem `Gate::authorize()`.
 */
final readonly class ProcessarAnaliseTextoDocumentoAction
{
    public function __construct(
        private ReivindicarDocumentoEmEtapaAction $reivindicar,
        private ExtractorTextoNativo $extractorTexto,
        private RegistarEtapaExtracaoAction $registarEtapa,
        private RegistarFalhaTecnicaExtracaoAction $registarFalhaTecnica,
        private MarcarAnaliseOcrDocumentoAction $marcarAnaliseOcr,
        private MarcarAnaliseIaLocalDocumentoAction $marcarAnaliseIaLocal,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(): ?Documento
    {
        $documento = $this->reivindicar->handle(EstadoDocumento::AnaliseTexto);

        if (! $documento instanceof Documento) {
            return null;
        }

        if (! $this->ePdf($documento)) {
            $this->registarEtapa->handle($documento, new RegistarEtapaExtracaoDto(
                resultado: ResultadoEtapa::Sucesso,
                motivo: 'ficheiro não-PDF: parser nativo ignorado, encaminhado para OCR',
            ));

            return $this->marcarAnaliseOcr->handle($documento, 'ficheiro não-PDF, encaminhado para OCR');
        }

        try {
            $resultado = $this->extractorTexto->extrair($this->caminhoAbsoluto($documento));
        } catch (FalhaExtracaoTextoException $erro) {
            return $this->registarFalhaTecnica->handle($documento, $erro->getMessage());
        }

        $this->registarEtapa->handle($documento, new RegistarEtapaExtracaoDto(
            resultado: ResultadoEtapa::Sucesso,
            motivo: 'texto nativo extraído',
            textoExtraido: $resultado->texto,
        ));

        return $resultado->ultrapassaThreshold === true
            ? $this->marcarAnaliseIaLocal->handle($documento)
            : $this->marcarAnaliseOcr->handle($documento);
    }

    private function ePdf(Documento $documento): bool
    {
        return Str::lower(pathinfo($documento->nome_ficheiro_storage, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function caminhoAbsoluto(Documento $documento): string
    {
        return Storage::disk($documento->disco_storage)->path($documento->nome_ficheiro_storage);
    }
}
