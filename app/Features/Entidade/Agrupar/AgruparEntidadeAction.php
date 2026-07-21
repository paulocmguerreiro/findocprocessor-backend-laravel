<?php

declare(strict_types=1);

namespace App\Features\Entidade\Agrupar;

use App\Models\Entidade;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Funde a entidade `secundaria` na `principal`: reponta todas as FKs conhecidas,
 * une os papéis por OR e remove permanentemente a secundária (hard-delete). Tudo
 * numa única transação — qualquer falha faz rollback total.
 */
final readonly class AgruparEntidadeAction
{
    /**
     * Allow-list explícita das colunas FK repontadas, no formato `"tabela.coluna"`.
     * Uma FK nova para `entidades` obriga a actualizar deliberadamente esta lista
     * — nunca é repontada às cegas (ver guarda de futuro em `handle`).
     *
     * @var list<string>
     */
    private const array COLUNAS_TRATADAS = ['documentos.id_fornecedor', 'documentos.id_cliente'];

    public function __construct(
        private InventarioReferenciasEntidadeInterface $inventarioReferencias,
        private CacheServico $cache,
    ) {}

    /**
     * @throws ModelNotFoundException<Entidade>
     * @throws AuthorizationException
     * @throws AgrupamentoInvalidoException
     * @throws \Throwable
     */
    public function handle(Entidade|string $principal, Entidade|string $secundaria): Entidade
    {
        $entidadePrincipal = is_string($principal) ? Entidade::findOrFail($principal) : $principal;
        $entidadeSecundaria = is_string($secundaria) ? Entidade::findOrFail($secundaria) : $secundaria;

        Gate::authorize('agrupar', Entidade::class);

        if ($entidadePrincipal->id === $entidadeSecundaria->id) {
            throw AgrupamentoInvalidoException::paraEntidadesIguais();
        }

        if ($entidadeSecundaria->e_empresa_aplicacao) {
            throw AgrupamentoInvalidoException::paraEmpresaAplicacao();
        }

        Log::info('entidade.agrupar.inicio', [
            'id_utilizador' => Auth::id(),
            'id_principal' => $entidadePrincipal->id,
            'id_secundaria' => $entidadeSecundaria->id,
        ]);

        $entidadePrincipal = DB::transaction(function () use ($entidadePrincipal, $entidadeSecundaria): Entidade {
            $referenciasNaoTratadas = array_values(array_diff(
                $this->inventarioReferencias->detectarColunasQueReferenciamEntidades(),
                self::COLUNAS_TRATADAS,
            ));

            if ($referenciasNaoTratadas !== []) {
                throw AgrupamentoInvalidoException::paraReferenciasNaoTratadas($referenciasNaoTratadas);
            }

            foreach (self::COLUNAS_TRATADAS as $referencia) {
                [$tabela, $coluna] = explode('.', $referencia, 2);

                DB::table($tabela)
                    ->where($coluna, $entidadeSecundaria->id)
                    ->update([$coluna => $entidadePrincipal->id]);
            }

            $entidadePrincipal->update([
                'e_cliente' => $entidadePrincipal->e_cliente || $entidadeSecundaria->e_cliente,
                'e_fornecedor' => $entidadePrincipal->e_fornecedor || $entidadeSecundaria->e_fornecedor,
            ]);

            $entidadeSecundaria->forceDelete();

            $this->cache->invalidarCache(TagCache::Entidades);

            return $entidadePrincipal->refresh();
        });

        Log::info('entidade.agrupar.fim', [
            'id_utilizador' => Auth::id(),
            'id_principal' => $entidadePrincipal->id,
        ]);

        return $entidadePrincipal;
    }
}
