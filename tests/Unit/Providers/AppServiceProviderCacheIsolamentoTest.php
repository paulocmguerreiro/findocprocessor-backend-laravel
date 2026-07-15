<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Cache;

it('gera prefixo com o token do processo', function (): void {
    expect(AppServiceProvider::prefixoCacheParalelo('app-cache-', 3))->toBe('app-cache-test_3_');
});

it('isola chaves de cache entre dois tokens diferentes', function (): void {
    $prefixoOriginal = config()->string('cache.prefix');
    $prefixoToken1 = AppServiceProvider::prefixoCacheParalelo($prefixoOriginal, 1);
    $prefixoToken2 = AppServiceProvider::prefixoCacheParalelo($prefixoOriginal, 2);

    try {
        config(['cache.prefix' => $prefixoToken1]);
        Cache::purge('redis');
        Cache::put('chave-teste', 'valor-processo-1', 60);

        config(['cache.prefix' => $prefixoToken2]);
        Cache::purge('redis');

        expect(Cache::get('chave-teste'))->toBeNull();
    } finally {
        config(['cache.prefix' => $prefixoToken1]);
        Cache::purge('redis');
        Cache::store('redis')->flush();

        config(['cache.prefix' => $prefixoToken2]);
        Cache::purge('redis');
        Cache::store('redis')->flush();

        config(['cache.prefix' => $prefixoOriginal]);
        Cache::purge('redis');
    }
});
