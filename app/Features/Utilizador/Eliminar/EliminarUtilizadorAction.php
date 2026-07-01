<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Eliminar;

use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class EliminarUtilizadorAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws AuthorizationException
     * @throws \DomainException
     * @throws \Throwable
     */
    public function handle(User $utilizador): void
    {
        Gate::authorize('delete', $utilizador);

        if (Auth::id() === $utilizador->id) {
            throw new \DomainException('Não é possível eliminar o próprio utilizador.');
        }

        Log::info('utilizador.eliminar.inicio', ['id_utilizador' => Auth::id(), 'id_alvo' => $utilizador->id]);

        DB::transaction(function () use ($utilizador): void {
            $utilizador->tokens()->delete();

            try {
                $utilizador->forceDelete();
            } catch (QueryException) {
                $utilizador->delete();
            }

            $this->cache->invalidarCache(TagCache::Utilizadores);
        });

        Log::info('utilizador.eliminar.fim', ['id_utilizador' => Auth::id(), 'id_alvo' => $utilizador->id]);
    }
}
