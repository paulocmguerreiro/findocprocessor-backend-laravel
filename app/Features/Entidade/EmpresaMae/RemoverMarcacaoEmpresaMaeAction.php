<?php

declare(strict_types=1);

namespace App\Features\Entidade\EmpresaMae;

use App\Models\Entidade;
use Illuminate\Support\Facades\DB;

final class RemoverMarcacaoEmpresaMaeAction
{
    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        DB::transaction(function (): void {
            Entidade::whereEmpresaAplicacao()->update(['e_empresa_aplicacao' => false]);
        });
    }
}
