<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $problemDetails = function (int $status, string $detail, array $extra = []): JsonResponse {
            return response()->json(array_merge(['status' => $status, 'detail' => $detail], $extra), $status);
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($problemDetails): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $problemDetails(422, 'Os dados fornecidos são inválidos.', ['errors' => $e->errors()]);
        });

        $exceptions->render(function (ModelNotFoundException $_e, Request $request) use ($problemDetails): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $problemDetails(404, 'Recurso não encontrado.');
        });

        $exceptions->render(function (AuthorizationException $_e, Request $request) use ($problemDetails): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $problemDetails(403, 'Sem permissão para aceder a este recurso.');
        });

        $exceptions->render(function (AuthenticationException $_e, Request $request) use ($problemDetails): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $problemDetails(401, 'Não autenticado.');
        });

        $exceptions->render(function (Throwable $_e, Request $request) use ($problemDetails): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $problemDetails(500, 'Ocorreu um erro interno. Tente novamente mais tarde.');
        });
    })->create();
