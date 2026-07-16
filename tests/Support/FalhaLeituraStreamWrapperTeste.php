<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Stream wrapper que simula uma falha de leitura de ficheiro (`fread()` a
 * devolver `false`) — cenário raro de reproduzir com um ficheiro real.
 */
final class FalhaLeituraStreamWrapperTeste
{
    /** @var resource|null */
    public $context;

    public function stream_open(): bool
    {
        return true;
    }

    public function stream_read(): false
    {
        return false;
    }

    public function stream_eof(): bool
    {
        return false;
    }
}
