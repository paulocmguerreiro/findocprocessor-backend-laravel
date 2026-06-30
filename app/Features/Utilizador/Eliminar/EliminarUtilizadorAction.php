<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Eliminar;

use App\Models\Documento;
use App\Models\EtapaDocumento;
use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
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

            // Padrão B: hard delete quando não há referências; soft delete (preservando
            // a autoria) quando o utilizador é responsável por documentos ou registou
            // etapas. A decisão é tomada por pré-verificação determinística — o
            // restrictOnDelete das FKs filhas é a salvaguarda ao nível da BD.
            // Anonimização RGPD dos dados pessoais no ramo soft delete: Issue #73.
            $this->estaReferenciado($utilizador)
                ? $utilizador->delete()
                : $utilizador->forceDelete();

            $this->cache->invalidarCache(TagCache::Utilizadores);
        });

        Log::info('utilizador.eliminar.fim', ['id_utilizador' => Auth::id(), 'id_alvo' => $utilizador->id]);
    }

    private function estaReferenciado(User $utilizador): bool
    {
        return Documento::where('id_responsavel', $utilizador->id)->exists()
            || EtapaDocumento::where('id_utilizador', $utilizador->id)->exists();
    }
}
