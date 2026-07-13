<?php

declare(strict_types=1);

namespace App\Features\Documento\Reivindicar;

use App\Features\Documento\MarcarAguardaEnvio\MarcarAguardaEnvioDocumentoAction;
use App\Models\Documento;
use Illuminate\Support\Facades\DB;

/**
 * Reivindicação de um `Documento` `Pendente` por um worker do pipeline: bloqueia
 * (`lockForUpdate()`) a linha dentro da própria transacção, evitando que dois
 * workers reivindiquem o mesmo documento em simultâneo. A `RegraTransicaoEstado`
 * (via `MarcarAguardaEnvioDocumentoAction`) actua como último nível de validação.
 *
 * Componente reutilizável, sem Job concreto nesta issue (#90) — invocado pela
 * issue futura do orquestrador. Acção de sistema/pipeline: sem `Gate::authorize()`,
 * mesmo padrão de `MarcarAguardaEnvioDocumentoAction`.
 */
final readonly class ReivindicarDocumentoPendenteAction
{
    public function __construct(private MarcarAguardaEnvioDocumentoAction $marcarAguardaEnvio) {}

    /**
     * @throws \Throwable
     */
    public function handle(): ?Documento
    {
        return DB::transaction(function (): ?Documento {
            $documento = Documento::query()->wherePendente()->lockForUpdate()->first();

            if (! $documento instanceof Documento) {
                return null;
            }

            return $this->marcarAguardaEnvio->handle($documento);
        });
    }
}
