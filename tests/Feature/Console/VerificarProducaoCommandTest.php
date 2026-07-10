<?php

declare(strict_types=1);

/**
 * Coloca toda a configuração num estado pronto para produção; cada teste
 * degrada uma única verificação para isolar o motivo da falha.
 */
function configurarProducaoValida(): void
{
    config()->set('app.debug', false);
    config()->set('app.env', 'production');
    config()->set('app.key', 'base64:chave-de-teste');
    config()->set('app.url', 'https://api.exemplo.pt');
    config()->set('sanctum.expiration', 480);
    config()->set('cors.allowed_origins', ['https://app.exemplo.pt']);
    config()->set('database.default', 'mysql');
    config()->set('database.redis.default.password', 'segredo-forte');
}

it('passa com exit code 0 quando toda a configuração está pronta para produção', function (): void {
    configurarProducaoValida();

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[OK]    APP_DEBUG desactivado')
        ->expectsOutputToContain('Todas as verificações passaram — configuração pronta para produção.')
        ->assertSuccessful();
});

it('falha quando APP_DEBUG está activo', function (): void {
    configurarProducaoValida();
    config()->set('app.debug', true);

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] APP_DEBUG desactivado')
        ->expectsOutputToContain('Verificações falhadas: 1')
        ->assertFailed();
});

it('falha quando APP_ENV não é production', function (): void {
    configurarProducaoValida();
    config()->set('app.env', 'local');

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] APP_ENV é production')
        ->assertFailed();
});

it('falha quando APP_KEY está vazia', function (): void {
    configurarProducaoValida();
    config()->set('app.key', '');

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] APP_KEY definida')
        ->assertFailed();
});

it('falha quando APP_URL não usa HTTPS', function (): void {
    configurarProducaoValida();
    config()->set('app.url', 'http://api.exemplo.pt');

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] APP_URL usa HTTPS')
        ->assertFailed();
});

it('falha quando a expiração dos tokens Sanctum excede o máximo', function (): void {
    configurarProducaoValida();
    config()->set('sanctum.expiration', 525600);

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] Tokens Sanctum expiram (máx. 480 minutos)')
        ->assertFailed();
});

it('falha quando os tokens Sanctum não expiram (expiração nula)', function (): void {
    configurarProducaoValida();
    config()->set('sanctum.expiration');

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] Tokens Sanctum expiram (máx. 480 minutos)')
        ->assertFailed();
});

it('falha quando o CORS permite qualquer origem (wildcard)', function (): void {
    configurarProducaoValida();
    config()->set('cors.allowed_origins', ['*']);

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] CORS sem wildcard, localhost ou origens vazias')
        ->assertFailed();
});

it('falha quando o CORS permite localhost', function (): void {
    configurarProducaoValida();
    config()->set('cors.allowed_origins', ['https://app.exemplo.pt', 'http://localhost:4200']);

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] CORS sem wildcard, localhost ou origens vazias')
        ->assertFailed();
});

it('falha quando o CORS permite 127.0.0.1', function (): void {
    configurarProducaoValida();
    config()->set('cors.allowed_origins', ['http://127.0.0.1:4200']);

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] CORS sem wildcard, localhost ou origens vazias')
        ->assertFailed();
});

it('falha quando o CORS não tem origens definidas', function (): void {
    configurarProducaoValida();
    config()->set('cors.allowed_origins', []);

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] CORS sem wildcard, localhost ou origens vazias')
        ->assertFailed();
});

it('falha quando o CORS tem uma origem que não é string', function (): void {
    configurarProducaoValida();
    config()->set('cors.allowed_origins', [4200]);

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] CORS sem wildcard, localhost ou origens vazias')
        ->assertFailed();
});

it('falha quando a base de dados é SQLite', function (): void {
    configurarProducaoValida();
    config()->set('database.default', 'sqlite');

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] Base de dados não é SQLite')
        ->assertFailed();
});

it('falha quando o Redis não tem password', function (): void {
    configurarProducaoValida();
    config()->set('database.redis.default.password');

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('[FALHA] Redis exige password')
        ->assertFailed();
});

it('acumula o total de verificações falhadas', function (): void {
    configurarProducaoValida();
    config()->set('app.debug', true);
    config()->set('app.env', 'local');
    config()->set('database.default', 'sqlite');

    $this->artisan('verificar:producao')
        ->expectsOutputToContain('Verificações falhadas: 3 — a configuração não está pronta para produção.')
        ->assertFailed();
});
