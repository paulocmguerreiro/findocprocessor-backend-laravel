<?php

declare(strict_types=1);

it('serves the interactive Swagger UI docs page outside production', function (): void {
    $resposta = $this->get('/docs');

    $resposta->assertOk();
    $resposta->assertSee('swagger-ui', escape: false);
});

it('serves the OpenAPI spec as yaml outside production', function (): void {
    $resposta = $this->get('/openapi.yaml');

    $resposta->assertOk();
    $resposta->assertHeader('Content-Type', 'application/yaml');
});
