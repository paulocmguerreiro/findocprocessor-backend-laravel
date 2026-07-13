<?php

declare(strict_types=1);

/**
 * Servidor `clamd` falso para testes: aceita uma ligação INSTREAM e responde
 * de acordo com o modo pedido. Usado por ClamAvAnalisadorMalwareTest para
 * exercitar o protocolo completo (incl. falhas de escrita/timeout) sem
 * depender de um `clamd` real (RNF-02).
 *
 * Modos (argv[1]):
 * - "responder" <resposta>            — drena os chunks e responde com <resposta>.
 * - "atrasar" <segundos> <resposta>   — drena os chunks, espera <segundos> e só depois responde.
 * - "fechar_imediato"                 — aceita a ligação e fecha-a sem ler nada.
 */

/**
 * @param  resource  $stream
 */
function lerExactamente($stream, int $tamanho): string
{
    $dados = '';

    while (strlen($dados) < $tamanho) {
        $lido = fread($stream, $tamanho - strlen($dados));

        if ($lido === false || $lido === '') {
            break;
        }

        $dados .= $lido;
    }

    return $dados;
}

/**
 * @param  resource  $stream
 */
function drenarInstream($stream): void
{
    lerExactamente($stream, 10); // "zINSTREAM\0"

    while (true) {
        $prefixo = lerExactamente($stream, 4);

        if (strlen($prefixo) < 4) {
            break;
        }

        /** @var array{1: int} $desempacotado */
        $desempacotado = unpack('N', $prefixo);
        $tamanhoChunk = $desempacotado[1];

        if ($tamanhoChunk === 0) {
            break;
        }

        lerExactamente($stream, $tamanhoChunk);
    }
}

$modo = $argv[1] ?? 'responder';

$servidor = stream_socket_server('tcp://127.0.0.1:0', $codigoErro, $mensagemErro);

if ($servidor === false) {
    fwrite(STDERR, "erro ao arrancar o servidor falso: {$mensagemErro}\n");
    exit(1);
}

$nome = stream_socket_get_name($servidor, false);
$porta = substr($nome, (int) strrpos($nome, ':') + 1);

echo $porta."\n";
fflush(STDOUT);

$cliente = stream_socket_accept($servidor, 10);

if ($cliente === false) {
    exit(1);
}

match ($modo) {
    'fechar_imediato' => null,
    'atrasar' => (function () use ($cliente, $argv): void {
        drenarInstream($cliente);
        usleep((int) ((float) $argv[2] * 1_000_000));
        fwrite($cliente, $argv[3]);
    })(),
    default => (function () use ($cliente, $argv): void {
        drenarInstream($cliente);
        fwrite($cliente, $argv[2] ?? 'stream: OK');
    })(),
};

fclose($cliente);
fclose($servidor);
