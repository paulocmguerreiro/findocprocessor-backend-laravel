<?php

declare(strict_types=1);

use App\Http\Middleware\InjectarContextoLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

it('injicta trace_id UUID no contexto por cada pedido', function (): void {
    Context::flush();

    $middleware = new InjectarContextoLog;
    $request = Request::create('/api/test', 'GET');

    $middleware->handle($request, fn (): Response => new Response);

    $traceId = Context::get('trace_id');
    expect($traceId)->not->toBeNull();
    expect(Str::isUuid((string) $traceId))->toBeTrue();
});

it('chama o próximo middleware na cadeia', function (): void {
    $middleware = new InjectarContextoLog;
    $request = Request::create('/api/test', 'GET');

    $chamado = false;
    $middleware->handle($request, function () use (&$chamado): Response {
        $chamado = true;

        return new Response;
    });

    expect($chamado)->toBeTrue();
});

it('gera um trace_id diferente para cada pedido', function (): void {
    Context::flush();

    $middleware = new InjectarContextoLog;
    $request = Request::create('/api/test', 'GET');

    $middleware->handle($request, fn (): Response => new Response);
    $primeiroId = Context::get('trace_id');

    Context::flush();

    $middleware->handle($request, fn (): Response => new Response);
    $segundoId = Context::get('trace_id');

    expect($primeiroId)->not->toBe($segundoId);
});
