<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

/**
 * Contrato do cliente de extração por IA — converte texto extraído em dados
 * estruturados (veredicto tipado), consoante a camada pedida pelo chamador.
 * Excepções da implementação concreta (timeout, erro HTTP, JSON malformado)
 * são sempre capturadas e devolvidas como `ResultadoExtracaoIA::falhaTecnica()`
 * — nunca propagadas (RF-07.4 da Spec).
 */
interface ContratoClienteIA
{
    public function extrair(string $textoExtraido, CamadaIA $camada): ResultadoExtracaoIA;
}
