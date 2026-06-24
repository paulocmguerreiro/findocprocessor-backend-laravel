<?php

declare(strict_types=1);

namespace App\Features\Entidade\Ver;

use App\Models\Entidade;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

final readonly class VerEntidadeAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<Entidade>
     * @throws AuthorizationException
     */
    public function handle(Entidade|string $idEntidade): Entidade
    {
        /** @var Entidade $entidade */
        $entidade = is_string($idEntidade)
            ? Entidade::findOrFail($idEntidade)
            : $idEntidade;

        Gate::authorize('view', $entidade);

        $chave = $this->cache->criarChave(TagCache::Entidades, TagOperacao::Ver, ['id' => $entidade->id]);

        /** @var Entidade $resultado */
        $resultado = $this->cache->lembrar(
            TagCache::Entidades,
            $chave,
            TtlCache::Media,
            fn (): Entidade => $entidade,
        );

        return $resultado;
    }
}
