<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Restaurar;

use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class RestaurarUtilizadorAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<User>
     * @throws AuthorizationException
     * @throws \DomainException
     * @throws \Throwable
     */
    public function handle(User|int $utilizador): User
    {
        $utilizador = is_int($utilizador)
            ? User::withTrashed()->findOrFail($utilizador)
            : $utilizador;

        Gate::authorize('restore', $utilizador);

        if (! $utilizador->trashed()) {
            throw new \DomainException('Utilizador não está inactivo.');
        }

        if (str_starts_with($utilizador->email, 'anonimizado+')) {
            throw new \DomainException('Utilizador anonimizado não pode ser restaurado.');
        }

        DB::transaction(function () use ($utilizador): void {
            $utilizador->restore();
            $this->cache->invalidarCache(TagCache::Utilizadores);
        });

        return $utilizador->load('roles');
    }
}
