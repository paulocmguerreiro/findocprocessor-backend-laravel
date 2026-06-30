<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Criar;

use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class CriarUtilizadorAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(CriarUtilizadorDto $dados): User
    {
        Gate::authorize('create', User::class);

        Log::info('utilizador.criar.inicio', ['id_utilizador' => Auth::id()]);

        $utilizador = DB::transaction(function () use ($dados): User {
            $utilizador = User::create([
                'name' => $dados->nome,
                'email' => $dados->email,
                'password' => $dados->password,
            ]);

            if ($dados->role !== null) {
                $utilizador->assignRole($dados->role);
            }

            $this->cache->invalidarCache(TagCache::Utilizadores);

            return $utilizador;
        });

        Log::info('utilizador.criar.fim', ['id_utilizador' => Auth::id(), 'id_novo' => $utilizador->id]);

        return $utilizador->load('roles');
    }
}
