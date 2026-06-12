<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

final class ApiResponse
{
    public static function devolverSucesso(JsonResource $recurso): JsonResponse
    {
        return response()->json(['data' => $recurso], Response::HTTP_OK);
    }

    public static function devolverCriado(JsonResource $recurso): JsonResponse
    {
        return response()->json(['data' => $recurso], Response::HTTP_CREATED);
    }

    public static function devolverVazio(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** @param array<string, string|int> $meta */
    public static function devolverColeccao(ResourceCollection $coleccao, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $coleccao->collection ?? new Collection(),
            'meta' => $meta,
        ], Response::HTTP_OK);
    }
}
