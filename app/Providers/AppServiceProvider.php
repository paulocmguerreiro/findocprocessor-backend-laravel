<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Malware\AnalisadorMalware;
use App\Infrastructure\Malware\ClamAvAnalisadorMalware;
use App\Observers\RoleObserver;
use App\Policies\RolePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(AnalisadorMalware::class, fn (): ClamAvAnalisadorMalware => new ClamAvAnalisadorMalware(
            host: config()->string('pipeline.malware.host'),
            port: config()->integer('pipeline.malware.port'),
            timeoutSegundos: config()->integer('pipeline.malware.timeout_segundos'),
        ));
    }

    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        Role::observe(RoleObserver::class);

        $this->configurarRateLimiters();
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
