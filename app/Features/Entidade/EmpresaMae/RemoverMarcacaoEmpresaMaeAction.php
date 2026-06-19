<?php

declare(strict_types=1);

namespace App\Features\Entidade\EmpresaMae;

use App\Models\Entidade;

final class RemoverMarcacaoEmpresaMaeAction
{
    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        Entidade::whereEmpresaAplicacao()->update(['e_empresa_aplicacao' => false]);
    }
}
