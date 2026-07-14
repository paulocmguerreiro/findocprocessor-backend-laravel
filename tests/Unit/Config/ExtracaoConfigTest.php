<?php

declare(strict_types=1);

/**
 * Escreve em $_ENV/$_SERVER e no process env real (putenv()): o env() do
 * Laravel cai para getenv() quando $_ENV/$_SERVER não têm a chave, pelo que
 * limpar só os arrays não basta — se a var já existir no processo (ex: .env
 * local do developer com valores reais para o Valet, herdado por engano no
 * ambiente de testes), getenv() continua a devolvê-la. putenv() garante que
 * o teste controla o estado real, independentemente do que estiver fora dele.
 *
 * @param  array<string, string>  $vars
 */
function definirVarsExtracao(array $vars): void
{
    foreach ($vars as $chave => $valor) {
        $_ENV[$chave] = $valor;
        $_SERVER[$chave] = $valor;
        putenv("{$chave}={$valor}");
    }
}

/**
 * @param  list<string>  $chaves
 */
function limparVarsExtracao(array $chaves): void
{
    foreach ($chaves as $chave) {
        unset($_ENV[$chave], $_SERVER[$chave]);
        putenv($chave);
    }
}

beforeEach(function (): void {
    limparVarsExtracao(['LLM_LOCAL_URL', 'LLM_LOCAL_MODEL', 'LLM_CLOUD_URL', 'LLM_CLOUD_MODEL', 'LLM_CLOUD_KEY']);
});

afterEach(function (): void {
    limparVarsExtracao(['LLM_LOCAL_URL', 'LLM_LOCAL_MODEL', 'LLM_CLOUD_URL', 'LLM_CLOUD_MODEL', 'LLM_CLOUD_KEY']);
});

it('activa a camada local quando url e modelo estão preenchidos', function (): void {
    definirVarsExtracao(['LLM_LOCAL_URL' => 'http://localhost:11434/v1', 'LLM_LOCAL_MODEL' => 'llama3']);

    $config = require config_path('extracao.php');

    expect($config['camada_local_activa'])->toBeTrue();
});

it('desactiva a camada local quando falta o modelo', function (): void {
    definirVarsExtracao(['LLM_LOCAL_URL' => 'http://localhost:11434/v1']);

    $config = require config_path('extracao.php');

    expect($config['camada_local_activa'])->toBeFalse();
});

it('desactiva a camada local quando nenhuma var está preenchida', function (): void {
    $config = require config_path('extracao.php');

    expect($config['camada_local_activa'])->toBeFalse();
});

it('activa a camada cloud quando url, modelo e key estão preenchidos', function (): void {
    definirVarsExtracao([
        'LLM_CLOUD_URL' => 'https://api.exemplo.com/v1',
        'LLM_CLOUD_MODEL' => 'gpt-4o',
        'LLM_CLOUD_KEY' => 'segredo',
    ]);

    $config = require config_path('extracao.php');

    expect($config['camada_cloud_activa'])->toBeTrue();
});

it('desactiva a camada cloud quando falta a key', function (): void {
    definirVarsExtracao([
        'LLM_CLOUD_URL' => 'https://api.exemplo.com/v1',
        'LLM_CLOUD_MODEL' => 'gpt-4o',
    ]);

    $config = require config_path('extracao.php');

    expect($config['camada_cloud_activa'])->toBeFalse();
});

it('define os valores fixos de threshold, ttl e tentativas', function (): void {
    $config = require config_path('extracao.php');

    expect($config['threshold_caracteres'])->toBe(50)
        ->and($config['max_tentativas'])->toBe(3)
        ->and($config['ttl_lease'])->toBe(300);
});

it('define os valores fixos de dpi e línguas do ocr', function (): void {
    $config = require config_path('extracao.php');

    expect($config['ocr']['dpi'])->toBe(300)
        ->and($config['ocr']['linguas'])->toBe(['por', 'eng']);
});
