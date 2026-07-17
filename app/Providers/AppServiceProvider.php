<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Malware\ClamAvAnalisadorMalware;
use App\Infrastructure\Malware\ContratoAnalisadorMalware;
use App\Observers\RoleObserver;
use App\Policies\RolePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(ContratoAnalisadorMalware::class, fn (): ClamAvAnalisadorMalware => new ClamAvAnalisadorMalware(
            host: config()->string('pipeline.malware.host'),
            port: config()->integer('pipeline.malware.port'),
            timeoutSegundos: config()->integer('pipeline.malware.timeout_segundos'),
        ));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Gate::policy(Role::class, RolePolicy::class);

        Role::observe(RoleObserver::class);

        $this->configurarRateLimiters();

        if ($this->app->runningUnitTests()) {
            // setUpTestCase() (não setUpProcess(), o exemplo oficial do Laravel): o Pest
            // corre --parallel com o próprio WrapperRunner (não o ParallelRunner do
            // Illuminate), que nunca chama callSetUpProcessCallbacks() — setUpProcess()
            // fica registado mas o closure nunca executa. setUpTestCase() é chamado
            // directamente por InteractsWithTestCaseLifecycle em cada teste, independente
            // do runner, por isso é o único hook fiável aqui.
            ParallelTesting::setUpTestCase([self::class, 'isolarCacheParalelo']);
        }
    }

    /**
     * Deriva um prefixo de cache exclusivo do processo Pest em paralelo, evitando que
     * processos distintos leiam/invalidem chaves Redis uns dos outros.
     */
    public static function prefixoCacheParalelo(string $prefixoBase, int $token): string
    {
        return "{$prefixoBase}test_{$token}_";
    }

    /**
     * Aplica o prefixo de cache exclusivo do token de processo Pest. Extraído do closure de
     * `setUpTestCase()` para um método nomeado: o `pcov` não regista de forma fiável a
     * cobertura de código só alcançável através da invocação indirecta de um hook de
     * framework — chamando este método directamente num teste garante 100% de cobertura.
     */
    public static function isolarCacheParalelo(int $token): void
    {
        config(['cache.prefix' => self::prefixoCacheParalelo(config()->string('cache.prefix'), $token)]);
        Cache::purge('redis');
    }

    /**
     * Limites de pedidos: um limite global por utilizador/IP para toda a API, um
     * limite estrito por email+IP no login (mitigação de brute-force) e um limite
     * dedicado ao upload (mais caro — `hash_file` sobre ficheiros até 10 MB).
     */
    private function configurarRateLimiters(): void
    {
        RateLimiter::for('api', fn (Request $pedido): Limit => Limit::perMinute(60)->by($pedido->user()?->getAuthIdentifier() ?? $pedido->ip()));

        RateLimiter::for('login', function (Request $pedido): Limit {
            $email = $pedido->input('email');
            $chave = (is_string($email) ? Str::lower($email) : '').'|'.$pedido->ip();

            return Limit::perMinute(5)->by($chave);
        });

        RateLimiter::for('upload', fn (Request $pedido): Limit => Limit::perMinute(20)->by($pedido->user()?->getAuthIdentifier() ?? $pedido->ip()));
    }
}
