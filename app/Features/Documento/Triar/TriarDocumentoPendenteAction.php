<?php

declare(strict_types=1);

namespace App\Features\Documento\Triar;

use App\Features\Documento\MarcarAguardaEnvio\MarcarAguardaEnvioDocumentoAction;
use App\Features\Documento\MarcarErro\MarcarErroDocumentoAction;
use App\Features\Documento\MarcarErro\MarcarErroDocumentoDto;
use App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoAction;
use App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoDto;
use App\Infrastructure\Malware\AnalisadorMalware;
use App\Infrastructure\Malware\FalhaAnaliseMalwareException;
use App\Models\Documento;
use Illuminate\Support\Facades\Storage;

/**
 * Scan de malware de um `Documento` `Pendente`, antes de avançar no pipeline
 * (issue #91). Invocada por `ReivindicarDocumentoPendenteAction` **dentro da
 * mesma transacção** que reivindica o Documento (RN-01) — não abre transacção
 * própria.
 *
 * - Infectado → `Perigoso` (motivo = assinatura devolvida pelo `clamd`).
 * - Limpo → `AguardaEnvio`.
 * - Não configurado (camada inactiva) → `AguardaEnvio`, com o motivo a
 *   registar que o scan estava desligado (RF-03/RN-02).
 * - Falha do scan (camada configurada mas o `clamd` falha) → `Erro`, com o
 *   motivo a registar a razão da falha (RF-04/RN-02).
 *
 * Transição de sistema: corre sempre em background (Jobs de extracção), sem
 * utilizador autenticado — não tem `Gate::authorize` (ver `02-shared/padroes-acoes.md`).
 */
final readonly class TriarDocumentoPendenteAction
{
    public function __construct(
        private AnalisadorMalware $analisador,
        private MarcarAguardaEnvioDocumentoAction $marcarAguardaEnvio,
        private MarcarPerigosoDocumentoAction $marcarPerigoso,
        private MarcarErroDocumentoAction $marcarErro,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento): Documento
    {
        $caminhoAbsoluto = Storage::disk($documento->disco_storage)->path($documento->nome_ficheiro_storage);

        try {
            $resultado = $this->analisador->analisar($caminhoAbsoluto);
        } catch (FalhaAnaliseMalwareException $erro) {
            return $this->marcarErro->handle($documento, new MarcarErroDocumentoDto($erro->getMessage()));
        }

        return match (true) {
            $resultado->estaInfectado() => $this->marcarPerigoso->handle(
                $documento,
                new MarcarPerigosoDocumentoDto($resultado->assinatura() ?? 'assinatura desconhecida'),
            ),
            $resultado->foiConfigurado() => $this->marcarAguardaEnvio->handle($documento),
            default => $this->marcarAguardaEnvio->handle($documento, 'scan de malware desligado'),
        };
    }
}
