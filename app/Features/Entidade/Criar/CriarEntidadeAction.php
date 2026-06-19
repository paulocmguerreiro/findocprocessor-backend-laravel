<?php

declare(strict_types=1);

namespace App\Features\Entidade\Criar;

use App\Features\Entidade\EmpresaMae\RegraUnicidadeEmpresaMae;
use App\Models\Entidade;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CriarEntidadeAction
{
    public function __construct(
        private RegraUnicidadeEmpresaMae $regraUnicidade,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(CriarEntidadeDto $dados): Entidade
    {
        Gate::authorize('create', Entidade::class);

        return DB::transaction(function () use ($dados): Entidade {
            $this->regraUnicidade->handle($dados->eEmpresaAplicacao);

            return Entidade::create([
                'nome' => $dados->nome,
                'nif' => $dados->nif,
                'e_cliente' => $dados->eClienteEfectivo(),
                'e_fornecedor' => $dados->eFornecedorEfectivo(),
                'e_empresa_aplicacao' => $dados->eEmpresaAplicacao,
            ]);
        });
    }
}
