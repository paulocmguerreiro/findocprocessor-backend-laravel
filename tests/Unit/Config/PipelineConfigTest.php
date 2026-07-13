<?php

declare(strict_types=1);

afterEach(function (): void {
    unset($_ENV['PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS'], $_SERVER['PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS']);
});

it('usa o default de 15 minutos quando a var não está definida', function (): void {
    $config = require config_path('pipeline.php');

    expect($config['reconciliacao_limiar_minutos'])->toBe(15);
});

it('usa o valor da var de ambiente quando definida', function (): void {
    $_ENV['PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS'] = '30';
    $_SERVER['PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS'] = '30';

    $config = require config_path('pipeline.php');

    expect($config['reconciliacao_limiar_minutos'])->toBe(30);
});
