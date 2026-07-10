<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Checklist de prontidão para produção: valida a configuração crítica de
 * segurança e falha com exit code 1 se alguma verificação chumbar. Pensado
 * para correr no deploy (entrypoint do Docker ou passo de CI) e bloquear
 * arranques com configuração insegura.
 */
#[Signature('verificar:producao')]
#[Description('Verifica a configuração crítica de produção — falha (exit 1) se alguma verificação chumbar')]
final class VerificarProducaoCommand extends Command
{
    private const int EXPIRACAO_MAXIMA_TOKEN_MINUTOS = 480;

    public function handle(): int
    {
        $totalFalhas = 0;

        foreach ($this->executarVerificacoes() as $descricao => $passou) {
            $this->line(sprintf('%s %s', $passou ? '[OK]   ' : '[FALHA]', $descricao));

            if (! $passou) {
                $totalFalhas++;
            }
        }

        if ($totalFalhas > 0) {
            $this->error(sprintf('Verificações falhadas: %d — a configuração não está pronta para produção.', $totalFalhas));

            return self::FAILURE;
        }

        $this->info('Todas as verificações passaram — configuração pronta para produção.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, bool>
     */
    private function executarVerificacoes(): array
    {
        return [
            'APP_DEBUG desactivado' => config()->boolean('app.debug') === false,
            'APP_ENV é production' => config()->string('app.env') === 'production',
            'APP_KEY definida' => config()->string('app.key', '') !== '',
            'APP_URL usa HTTPS' => str_starts_with(config()->string('app.url', ''), 'https://'),
            'Tokens Sanctum expiram (máx. '.self::EXPIRACAO_MAXIMA_TOKEN_MINUTOS.' minutos)' => $this->validarExpiracaoToken(),
            'CORS sem wildcard, localhost ou origens vazias' => $this->validarOrigensCors(),
            'Base de dados não é SQLite' => config()->string('database.default') !== 'sqlite',
            'Redis exige password' => $this->validarPasswordRedis(),
        ];
    }

    /**
     * Expiração `null`/0 significa tokens eternos — inaceitável em produção.
     */
    private function validarExpiracaoToken(): bool
    {
        $expiracaoMinutos = config('sanctum.expiration');

        return is_int($expiracaoMinutos)
            && $expiracaoMinutos >= 1
            && $expiracaoMinutos <= self::EXPIRACAO_MAXIMA_TOKEN_MINUTOS;
    }

    private function validarOrigensCors(): bool
    {
        $origens = config()->array('cors.allowed_origins');

        if ($origens === []) {
            return false;
        }

        foreach ($origens as $origem) {
            if (! is_string($origem)
                || $origem === '*'
                || str_contains($origem, 'localhost')
                || str_contains($origem, '127.0.0.1')) {
                return false;
            }
        }

        return true;
    }

    private function validarPasswordRedis(): bool
    {
        $password = config('database.redis.default.password');

        return is_string($password) && $password !== '';
    }
}
