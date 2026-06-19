<?php

declare(strict_types=1);

namespace App\Features\Entidade;

/**
 * @property-read bool $eEmpresaAplicacao
 * @property-read bool $eCliente
 * @property-read bool $eFornecedor
 */
trait ComFlagsEfectivosEmpresaMae
{
    public function eClienteEfectivo(): bool
    {
        return $this->eEmpresaAplicacao || $this->eCliente;
    }

    public function eFornecedorEfectivo(): bool
    {
        return $this->eEmpresaAplicacao || $this->eFornecedor;
    }
}
