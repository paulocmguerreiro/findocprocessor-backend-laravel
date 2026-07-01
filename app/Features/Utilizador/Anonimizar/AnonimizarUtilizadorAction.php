<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Anonimizar;

use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class AnonimizarUtilizadorAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws AuthorizationException
     * @throws \DomainException
     * @throws \Throwable
     */
    public function handle(User $utilizador): void
    {
        Gate::authorize('anonimizar', $utilizador);

        if (Auth::id() === $utilizador->id) {
            throw new \DomainException('Não é possível anonimizar o próprio utilizador.');
        }

        if (str_starts_with($utilizador->email, 'anonimizado+')) {
            throw new \DomainException('Utilizador já está anonimizado.');
        }

        DB::transaction(function () use ($utilizador): void {
            $utilizador->tokens()->delete();

            // saveQuietly() suprime o evento 'updated' do RegistaActividade, que
            // registaria old.name/old.email (PII) no activity_log. O evento de
            // anonimização é registado manualmente a seguir, sem campos.
            $utilizador->forceFill([
                'name' => 'Utilizador #'.$utilizador->id,
                'email' => 'anonimizado+'.$utilizador->id.'@removido.invalid',
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'email_verified_at' => null,
            ])->saveQuietly();

            activity()
                ->performedOn($utilizador)
                ->causedBy(Auth::user())
                ->event('rgpd.anonimizacao')
                ->log('utilizador anonimizado');

            $utilizador->delete();

            $this->cache->invalidarCache(TagCache::Utilizadores);
        });
    }
}
