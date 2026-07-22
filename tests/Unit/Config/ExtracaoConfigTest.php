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

const VARS_EXTRACAO = [
    'LLM_LOCAL_URL', 'LLM_LOCAL_MODEL', 'LLM_LOCAL_PROVIDER',
    'LLM_CLOUD_URL', 'LLM_CLOUD_MODEL', 'LLM_CLOUD_KEY', 'LLM_CLOUD_PROVIDER',
];

beforeEach(function (): void {
    limparVarsExtracao(VARS_EXTRACAO);
});

afterEach(function (): void {
    limparVarsExtracao(VARS_EXTRACAO);
});

it('activa a camada local quando url e modelo estão preenchidos', function (): void {
    definirVarsExtracao(['LLM_LOCAL_URL' => 'http://localhost:11434/v1', 'LLM_LOCAL_MODEL' => 'llama3']);

    $config = require config_path('extracao.php');

    expect($config['local']['activa'])->toBeTrue();
});

it('expõe o modelo local a partir de LLM_LOCAL_MODEL', function (): void {
    definirVarsExtracao(['LLM_LOCAL_MODEL' => 'llama3']);

    $config = require config_path('extracao.php');

    expect($config['local']['modelo'])->toBe('llama3');
});

it('expõe o modelo local como null quando LLM_LOCAL_MODEL não está preenchida', function (): void {
    $config = require config_path('extracao.php');

    expect($config['local']['modelo'])->toBeNull();
});

it('expõe o modelo cloud a partir de LLM_CLOUD_MODEL', function (): void {
    definirVarsExtracao(['LLM_CLOUD_MODEL' => 'gpt-4o']);

    $config = require config_path('extracao.php');

    expect($config['cloud']['modelo'])->toBe('gpt-4o');
});

it('expõe o modelo cloud como null quando LLM_CLOUD_MODEL não está preenchida', function (): void {
    $config = require config_path('extracao.php');

    expect($config['cloud']['modelo'])->toBeNull();
});

it('assume ollama como provider local por omissão', function (): void {
    $config = require config_path('extracao.php');

    expect($config['local']['provider'])->toBe('ollama');
});

it('permite substituir o provider local via LLM_LOCAL_PROVIDER', function (): void {
    definirVarsExtracao(['LLM_LOCAL_PROVIDER' => 'openrouter']);

    $config = require config_path('extracao.php');

    expect($config['local']['provider'])->toBe('openrouter');
});

it('assume anthropic como provider cloud por omissão', function (): void {
    $config = require config_path('extracao.php');

    expect($config['cloud']['provider'])->toBe('anthropic');
});

it('permite substituir o provider cloud via LLM_CLOUD_PROVIDER', function (): void {
    definirVarsExtracao(['LLM_CLOUD_PROVIDER' => 'openrouter']);

    $config = require config_path('extracao.php');

    expect($config['cloud']['provider'])->toBe('openrouter');
});

it('expõe a url local com omissão para o Ollama por defeito', function (): void {
    $config = require config_path('extracao.php');

    expect($config['local']['url'])->toBe('http://localhost:11434');
});

it('expõe a url local a partir de LLM_LOCAL_URL', function (): void {
    definirVarsExtracao(['LLM_LOCAL_URL' => 'http://ollama.interno:11434/v1']);

    $config = require config_path('extracao.php');

    expect($config['local']['url'])->toBe('http://ollama.interno:11434/v1');
});

it('expõe a url e a key cloud a partir de LLM_CLOUD_URL/LLM_CLOUD_KEY', function (): void {
    definirVarsExtracao(['LLM_CLOUD_URL' => 'https://api.anthropic.com/v1', 'LLM_CLOUD_KEY' => 'segredo']);

    $config = require config_path('extracao.php');

    expect($config['cloud']['url'])->toBe('https://api.anthropic.com/v1')
        ->and($config['cloud']['key'])->toBe('segredo');
});

it('expõe a url cloud como null e a key como string vazia quando não preenchidas', function (): void {
    $config = require config_path('extracao.php');

    expect($config['cloud']['url'])->toBeNull()
        ->and($config['cloud']['key'])->toBe('');
});

it('desactiva a camada local quando falta o modelo', function (): void {
    definirVarsExtracao(['LLM_LOCAL_URL' => 'http://localhost:11434/v1']);

    $config = require config_path('extracao.php');

    expect($config['local']['activa'])->toBeFalse();
});

it('desactiva a camada local quando nenhuma var está preenchida', function (): void {
    $config = require config_path('extracao.php');

    expect($config['local']['activa'])->toBeFalse();
});

it('activa a camada cloud quando url, modelo e key estão preenchidos', function (): void {
    definirVarsExtracao([
        'LLM_CLOUD_URL' => 'https://api.exemplo.com/v1',
        'LLM_CLOUD_MODEL' => 'gpt-4o',
        'LLM_CLOUD_KEY' => 'segredo',
    ]);

    $config = require config_path('extracao.php');

    expect($config['cloud']['activa'])->toBeTrue();
});

it('desactiva a camada cloud quando falta a key', function (): void {
    definirVarsExtracao([
        'LLM_CLOUD_URL' => 'https://api.exemplo.com/v1',
        'LLM_CLOUD_MODEL' => 'gpt-4o',
    ]);

    $config = require config_path('extracao.php');

    expect($config['cloud']['activa'])->toBeFalse();
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
