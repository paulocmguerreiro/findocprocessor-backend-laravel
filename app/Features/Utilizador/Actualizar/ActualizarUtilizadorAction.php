<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Actualizar;

use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class ActualizarUtilizadorAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(User $utilizador, ActualizarUtilizadorDto $dados): User
    {
        Gate::authorize('update', $utilizador);

        Log::info('utilizador.actualizar.inicio', ['id_utilizador' => Auth::id(), 'id_alvo' => $utilizador->id]);

        $utilizador = DB::transaction(function () use ($utilizador, $dados): User {
            $utilizador->fill([
                'name' => $dados->nome,
                'email' => $dados->email,
            ]);

            if ($dados->password !== null) {
                $utilizador->fill(['password' => $dados->password]);
            }

            $utilizador->save();
            $utilizador->refresh();

            $this->cache->invalidarCache(TagCache::Utilizadores);

            return $utilizador;
        });

        Log::info('utilizador.actualizar.fim', ['id_utilizador' => Auth::id(), 'id_alvo' => $utilizador->id]);

        return $utilizador->load('roles');
    }
}
