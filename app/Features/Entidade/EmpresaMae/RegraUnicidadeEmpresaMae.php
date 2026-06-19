<?php

declare(strict_types=1);

namespace App\Features\Entidade\EmpresaMae;

final readonly class RegraUnicidadeEmpresaMae
{
    public function __construct(
        private RemoverMarcacaoEmpresaMaeAction $removerMarcacao,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(bool $eEmpresaAplicacao): void
    {
        if ($eEmpresaAplicacao) {
            $this->removerMarcacao->handle();
        }
    }
}
