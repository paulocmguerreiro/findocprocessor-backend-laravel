<?php

declare(strict_types=1);

namespace App\Features\Documento\Triar;

use App\Features\Documento\MarcarAnaliseMalware\MarcarAnaliseMalwareDocumentoAction;
use App\Features\Documento\MarcarAnaliseTexto\MarcarAnaliseTextoDocumentoAction;
use App\Features\Documento\MarcarErro\MarcarErroDocumentoAction;
use App\Features\Documento\MarcarErro\MarcarErroDocumentoDto;
use App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoAction;
use App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoDto;
use App\Infrastructure\Malware\ContratoAnalisadorMalware;
use App\Infrastructure\Malware\FalhaAnaliseMalwareException;
use App\Models\Documento;
use Illuminate\Support\Facades\Storage;

/**
 * Triagem de malware de um `Documento` `Pendente` (issue #91). Admite-o primeiro a
 * `AnaliseMalware` (`Pendente → AnaliseMalware`), corre o scan sobre o mesmo ficheiro
 * (disco `entrada`) e ramifica conforme o veredicto. Invocada por
 * `ReivindicarDocumentoPendenteAction` **dentro da mesma transacção** que reivindica
 * o Documento (RN-01) — não abre transacção própria.
 *
 * - Infectado → `Perigoso` (motivo = assinatura devolvida pelo `clamd`).
 * - Limpo → `AnaliseTexto`.
 * - Não configurado (camada inactiva) → `AnaliseTexto`, com o motivo a registar que
 *   o scan estava desligado.
 * - Falha do scan (camada configurada mas o `clamd` falha) → `Erro`, com o motivo a
 *   registar a razão da falha.
 *
 * Transição de sistema: corre sempre em background (Jobs de extracção), sem
 * utilizador autenticado — não tem `Gate::authorize` (ver `02-shared/padroes-acoes.md`).
 */
final readonly class TriarDocumentoPendenteAction
{
    public function __construct(
        private ContratoAnalisadorMalware $analisador,
        private MarcarAnaliseMalwareDocumentoAction $marcarAnaliseMalware,
        private MarcarAnaliseTextoDocumentoAction $marcarAnaliseTexto,
        private MarcarPerigosoDocumentoAction $marcarPerigoso,
        private MarcarErroDocumentoAction $marcarErro,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento): Documento
    {
        $documento = $this->marcarAnaliseMalware->handle($documento);

        $caminhoAbsoluto = Storage::disk($documento->disco_storage)->path($documento->nome_ficheiro_storage);

        try {
            $resultadoScan = $this->analisador->analisar($caminhoAbsoluto);
        } catch (FalhaAnaliseMalwareException $erro) {
            return $this->marcarErro->handle($documento, new MarcarErroDocumentoDto($erro->getMessage()));
        }

        return match (true) {
            $resultadoScan->estaInfectado() => $this->marcarPerigoso->handle(
                $documento,
                new MarcarPerigosoDocumentoDto($resultadoScan->assinatura ?? 'assinatura desconhecida'),
            ),
            $resultadoScan->estaConfigurado() => $this->marcarAnaliseTexto->handle($documento),
            default => $this->marcarAnaliseTexto->handle($documento, 'scan de malware desligado'),
        };
    }
}
