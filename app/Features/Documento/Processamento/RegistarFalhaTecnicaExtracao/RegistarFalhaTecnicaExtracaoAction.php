<?php

declare(strict_types=1);

namespace App\Features\Documento\Processamento\RegistarFalhaTecnicaExtracao;

use App\Features\Documento\MarcarErro\MarcarErroDocumentoAction;
use App\Features\Documento\MarcarErro\MarcarErroDocumentoDto;
use App\Features\Documento\Processamento\RegistarEtapaExtracao\RegistarEtapaExtracaoAction;
use App\Features\Documento\Processamento\RegistarEtapaExtracao\RegistarEtapaExtracaoDto;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\ResultadoEtapa;

/**
 * Tecto de tentativas técnicas (RF-12), partilhado por todos os orquestradores de
 * etapa (`AnaliseTexto`/`AnaliseOcr`/`AnaliseIaLocal`/`AnaliseCloud`) para não
 * duplicar a mecânica: regista a falha técnica da etapa actual (incrementa
 * `extracao_tentativas` via o recorder) e, se atingir `config('extracao.max_tentativas')`,
 * transiciona o documento para `Erro`; caso contrário devolve-o inalterado (a etapa
 * é retentada no próximo ciclo).
 *
 * Só falhas **técnicas** (excepção do motor/cliente) passam por aqui — saltos
 * semânticos (threshold, veredicto) e por camada inactiva **não** incrementam (RN-04).
 * Acção de sistema — sem `Gate::authorize()`.
 */
final readonly class RegistarFalhaTecnicaExtracaoAction
{
    public function __construct(
        private RegistarEtapaExtracaoAction $registarEtapa,
        private MarcarErroDocumentoAction $marcarErro,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento, string $motivo): Documento
    {
        // O recorder é "substituição total": re-enviar o texto/dados já extraídos
        // preserva-os (uma etapa de IA que falha tecnicamente tem de manter o texto
        // do parser/OCR para o próximo ciclo poder reprocessar).
        $extracaoActual = ExtracaoDocumento::query()->where('id_documento', $documento->id)->first();

        $extracao = $this->registarEtapa->handle($documento, new RegistarEtapaExtracaoDto(
            resultado: ResultadoEtapa::Falha,
            motivo: $motivo,
            textoExtraido: $extracaoActual?->texto_extraido,
            dadosJson: $extracaoActual?->dados_json,
            incrementarTentativas: true,
        ));

        if ($extracao->extracao_tentativas >= config()->integer('extracao.max_tentativas')) {
            return $this->marcarErro->handle($documento, new MarcarErroDocumentoDto($motivo));
        }

        return $documento;
    }
}
