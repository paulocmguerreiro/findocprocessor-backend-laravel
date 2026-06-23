<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

            return $problemDetails(Response::HTTP_UNPROCESSABLE_ENTITY, 'Os dados fornecidos são inválidos.', ['errors' => $e->errors()]);
        });

        $exceptions->render(function (NotFoundHttpException $_e, Request $request) use ($problemDetails): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $problemDetails(Response::HTTP_NOT_FOUND, 'Recurso não encontrado.');
        });

        $exceptions->render(function (AccessDeniedHttpException $_e, Request $request) use ($problemDetails): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $problemDetails(Response::HTTP_FORBIDDEN, 'Sem permissão para aceder a este recurso.');
        });

        $exceptions->render(function (AuthenticationException $_e, Request $request) use ($problemDetails): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $problemDetails(Response::HTTP_UNAUTHORIZED, 'Não autenticado.');
        });

        $exceptions->render(function (DomainException $e, Request $request) use ($problemDetails): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $problemDetails(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage());
        });

        $exceptions->render(function (Throwable $_e, Request $request) use ($problemDetails): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return $problemDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Ocorreu um erro interno. Tente novamente mais tarde.');
        });
    })->create();
