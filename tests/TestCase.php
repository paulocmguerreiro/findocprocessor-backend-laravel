<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

abstract class TestCase extends BaseTestCase
{
    /**
     * Desliga o rate limiting por defeito: os contadores vivem na cache Redis
     * partilhada e sobreviveriam entre testes (execução `--parallel`), tornando
     * a suite não-determinística. Os testes que validam o throttle reactivam-no
     * explicitamente com `withMiddleware(ThrottleRequests::class)`.
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }
}
