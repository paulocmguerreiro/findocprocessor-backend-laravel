<?php

declare(strict_types=1);

namespace App\Features\Documento\Processamento\ProcessarAnaliseOcr;

use App\Features\Documento\Atribuicao\ReivindicarDocumentoEmEtapa\ReivindicarDocumentoEmEtapaAction;
use App\Features\Documento\Processamento\MarcarAnaliseIaLocal\MarcarAnaliseIaLocalDocumentoAction;
use App\Features\Documento\Processamento\RegistarEtapaExtracao\RegistarEtapaExtracaoAction;
use App\Features\Documento\Processamento\RegistarEtapaExtracao\RegistarEtapaExtracaoDto;
use App\Features\Documento\Processamento\RegistarFalhaTecnicaExtracao\RegistarFalhaTecnicaExtracaoAction;
use App\Infrastructure\Extracao\ExtractorOcr;
use App\Infrastructure\Extracao\FalhaExtracaoTextoException;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Enums\ResultadoEtapa;
use Illuminate\Support\Facades\Storage;

/**
 * Orquestrador da etapa `AnaliseOcr` (RF-06/RF-08/RF-12): reclama por lease o
 * próximo documento nesse estado, corre `ExtractorOcr::extrair()` (Tesseract) e,
 * em sucesso, regista o texto e encaminha para `AnaliseIaLocal`. Uma falha técnica
 * (`FalhaExtracaoTextoException`) é contabilizada por `RegistarFalhaTecnicaExtracaoAction`
 * (à `max_tentativas` → `Erro`). Acção de sistema — sem `Gate::authorize()`.
 */
final readonly class ProcessarAnaliseOcrDocumentoAction
{
    public function __construct(
        private ReivindicarDocumentoEmEtapaAction $reivindicar,
        private ExtractorOcr $extractorOcr,
        private RegistarEtapaExtracaoAction $registarEtapa,
        private RegistarFalhaTecnicaExtracaoAction $registarFalhaTecnica,
        private MarcarAnaliseIaLocalDocumentoAction $marcarAnaliseIaLocal,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(): ?Documento
    {
        $documento = $this->reivindicar->handle(EstadoDocumento::AnaliseOcr);

        if (! $documento instanceof Documento) {
            return null;
        }

        try {
            $resultado = $this->extractorOcr->extrair($this->caminhoAbsoluto($documento));
        } catch (FalhaExtracaoTextoException $erro) {
            return $this->registarFalhaTecnica->handle($documento, $erro->getMessage());
        }

        $this->registarEtapa->handle($documento, new RegistarEtapaExtracaoDto(
            resultado: ResultadoEtapa::Sucesso,
            motivo: 'texto extraído por OCR',
            textoExtraido: $resultado->texto,
        ));

        return $this->marcarAnaliseIaLocal->handle($documento);
    }

    private function caminhoAbsoluto(Documento $documento): string
    {
        return Storage::disk($documento->disco_storage)->path($documento->nome_ficheiro_storage);
    }
}
