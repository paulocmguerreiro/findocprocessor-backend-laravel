<?php

declare(strict_types=1);

use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('gera a mesma chave independentemente da ordem dos params', function (): void {
    $servico = new CacheServico();

    $chave1 = $servico->criarChave(TagCache::Entidades, TagOperacao::Listar, ['b' => '2', 'a' => '1']);
    $chave2 = $servico->criarChave(TagCache::Entidades, TagOperacao::Listar, ['a' => '1', 'b' => '2']);

    expect($chave1)->toBe($chave2);
});

it('inclui o id do utilizador na chave quando fornecido', function (): void {
    $utilizador = User::factory()->create();
    $servico = new CacheServico();

    $chave = $servico->criarChave(TagCache::Entidades, TagOperacao::Ver, ['id' => 'x'], $utilizador);

    expect($chave)->toContain("utilizador:{$utilizador->id}");
});

it('executa callback no miss e devolve valor cacheado no hit', function (): void {
    $servico = new CacheServico();
    $chave = $servico->criarChave(TagCache::Entidades, TagOperacao::Ver, ['id' => 'abc']);
    $contador = 0;

    $servico->lembrar(TagCache::Entidades, $chave, TtlCache::Media, function () use (&$contador): string {
        $contador++;

        return 'valor';
    });

    $resultado = $servico->lembrar(TagCache::Entidades, $chave, TtlCache::Media, function () use (&$contador): string {
        $contador++;

        return 'valor';
    });

    expect($contador)->toBe(1)->and($resultado)->toBe('valor');
});

it('invalida cache — callback volta a ser executado após flush', function (): void {
    $servico = new CacheServico();
    $chave = $servico->criarChave(TagCache::Entidades, TagOperacao::Listar, []);
    $contador = 0;
    $cb = function () use (&$contador): int { return ++$contador; };

    $servico->lembrar(TagCache::Entidades, $chave, TtlCache::Curta, $cb);
    $servico->invalidarCache(TagCache::Entidades);
    $servico->lembrar(TagCache::Entidades, $chave, TtlCache::Curta, $cb);

    expect($contador)->toBe(2);
});
