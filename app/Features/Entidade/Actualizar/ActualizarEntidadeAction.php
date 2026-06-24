<?php

declare(strict_types=1);

namespace App\Features\Entidade\Actualizar;

use App\Features\Entidade\EmpresaMae\RegraUnicidadeEmpresaMae;
use App\Models\Entidade;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class ActualizarEntidadeAction
{
    public function __construct(
        private RegraUnicidadeEmpresaMae $regraUnicidade,
        private CacheServico $cache,
    ) {}

    /**
     * @throws ModelNotFoundException<Entidade>
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Entidade|string $idEntidade, ActualizarEntidadeDto $dados): Entidade
    {
        /** @var Entidade $entidade */
        $entidade = is_string($idEntidade)
            ? Entidade::findOrFail($idEntidade)
            : $idEntidade;

        Gate::authorize('update', $entidade);

        return DB::transaction(function () use ($entidade, $dados): Entidade {
            $this->regraUnicidade->handle($dados->eEmpresaAplicacao);

            $entidade->fill([
                'nome' => $dados->nome,
                'nif' => $dados->nif,
                'e_cliente' => $dados->eClienteEfectivo(),
                'e_fornecedor' => $dados->eFornecedorEfectivo(),
                'e_empresa_aplicacao' => $dados->eEmpresaAplicacao,
            ])->save();

            $entidade->refresh();

            $this->cache->invalidarCache(TagCache::Entidades);

            return $entidade;
        });
    }
}
