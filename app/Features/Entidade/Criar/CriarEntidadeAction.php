<?php

declare(strict_types=1);

namespace App\Features\Entidade\Criar;

use App\Features\Entidade\EmpresaMae\RegraUnicidadeEmpresaMae;
use App\Models\Entidade;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class CriarEntidadeAction
{
    public function __construct(
        private RegraUnicidadeEmpresaMae $regraUnicidade,
        private CacheServico $cache,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(CriarEntidadeDto $dados): Entidade
    {
        Gate::authorize('create', Entidade::class);

        Log::info('entidade.criar.inicio', ['id_utilizador' => Auth::id()]);

        $entidade = DB::transaction(function () use ($dados): Entidade {
            $this->regraUnicidade->handle($dados->eEmpresaAplicacao);

            $entidade = Entidade::create([
                'nome' => $dados->nome,
                'nif' => $dados->nif,
                'e_cliente' => $dados->eClienteEfectivo(),
                'e_fornecedor' => $dados->eFornecedorEfectivo(),
                'e_empresa_aplicacao' => $dados->eEmpresaAplicacao,
            ]);

            $this->cache->invalidarCache(TagCache::Entidades);

            return $entidade;
        });

        Log::info('entidade.criar.fim', ['id_utilizador' => Auth::id(), 'id_entidade' => $entidade->id]);

        return $entidade;
    }
}
