<?php

declare(strict_types=1);

namespace App\Features\Entidade\EmpresaMae;

use App\Models\Entidade;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class ConverterEmEmpresaMaeAction
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
    public function handle(Entidade|string $idEntidade): Entidade
    {
        /** @var Entidade $entidade */
        $entidade = is_string($idEntidade)
            ? Entidade::findOrFail($idEntidade)
            : $idEntidade;

        Gate::authorize('update', $entidade);

        Log::info('entidade.converter_empresa_mae.inicio', ['id_utilizador' => Auth::id()]);

        $entidade = DB::transaction(function () use ($entidade): Entidade {
            $this->regraUnicidade->handle(true);

            $entidade->update([
                'e_empresa_aplicacao' => true,
                'e_cliente' => true,
                'e_fornecedor' => true,
            ]);

            $entidade->refresh();

            $this->cache->invalidarCache(TagCache::Entidades);

            return $entidade;
        });

        Log::info('entidade.converter_empresa_mae.fim', ['id_utilizador' => Auth::id(), 'id_entidade' => $entidade->id]);

        return $entidade;
    }
}
