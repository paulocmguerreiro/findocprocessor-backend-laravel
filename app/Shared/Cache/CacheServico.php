<?php

declare(strict_types=1);

namespace App\Shared\Cache;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class CacheServico
{
    /**
     * @param  array<string, int|string|null>  $params
     */
    public function criarChave(
        TagCache $tag,
        TagOperacao $operacao,
        array $params = [],
        ?User $utilizador = null,
    ): string {
        ksort($params);
        $hash = hash('sha256', http_build_query($params));

        if ($utilizador instanceof User) {
            return "{$tag->value}:utilizador:{$utilizador->id}:{$operacao->value}:{$hash}";
        }

        return "{$tag->value}:{$operacao->value}:{$hash}";
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function lembrar(
        TagCache $tag,
        string $chave,
        TtlCache $ttl,
        \Closure $callback,
        ?User $utilizador = null,
    ): mixed {
        $tags = [$tag->value];

        if ($utilizador instanceof User) {
            $tags[] = "{$tag->value}:utilizador:{$utilizador->id}";
        }

        return Cache::tags($tags)->remember($chave, $ttl->value, $callback);
    }

    public function invalidarCache(TagCache $tag, ?User $utilizador = null): void
    {
        if ($utilizador instanceof User) {
            Cache::tags(["{$tag->value}:utilizador:{$utilizador->id}"])->flush();

            return;
        }

        Cache::tags([$tag->value])->flush();
    }
}
