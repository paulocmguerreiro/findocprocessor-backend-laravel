<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento;

use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaAction;
use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaDto;
use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaAction;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaDto;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaRequest;
use App\Features\CategoriaDocumento\Eliminar\EliminarCategoriaAction;
use App\Features\CategoriaDocumento\Listar\CampoOrdenacaoCategorias;
use App\Features\CategoriaDocumento\Listar\ListarCategoriasAction;
use App\Features\CategoriaDocumento\Listar\ListarCategoriasRequest;
use App\Features\CategoriaDocumento\Ver\VerCategoriaAction;
use App\Http\Controllers\Controller;
use App\Models\CategoriaDocumento;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CategoriaDocumentoController extends Controller
{
    public function index(ListarCategoriasRequest $pedido, ListarCategoriasAction $accao): JsonResponse
    {
        /** @var array{per_page?: int, sort?: string, direction?: string} $validated */
        $validated = $pedido->validated();

        $porPagina = $validated['per_page'] ?? 15;
        $campoOrdenacao = CampoOrdenacaoCategorias::from($validated['sort'] ?? CampoOrdenacaoCategorias::Nome->value);
        $direcaoOrdenacao = DirecaoOrdenacao::from($validated['direction'] ?? DirecaoOrdenacao::Asc->value);

        $categorias = $accao->handle($porPagina, $campoOrdenacao, $direcaoOrdenacao);

        return ApiResponse::devolverPaginado(
            CategoriaDocumentoResource::collection($categorias),
        );
    }

    public function store(CriarCategoriaRequest $request, CriarCategoriaAction $accao): JsonResponse
    {
        $categoria = $accao->handle(CriarCategoriaDto::fromRequest($request));

        return ApiResponse::devolverCriado(new CategoriaDocumentoResource($categoria));
    }

    public function show(CategoriaDocumento $categorias_documento, VerCategoriaAction $accao): JsonResponse
    {
        return ApiResponse::devolverSucesso(
            new CategoriaDocumentoResource($accao->handle($categorias_documento)),
        );
    }

    public function update(ActualizarCategoriaRequest $request, CategoriaDocumento $categorias_documento, ActualizarCategoriaAction $accao): JsonResponse
    {
        $categoria = $accao->handle($categorias_documento, ActualizarCategoriaDto::fromRequest($request));

        return ApiResponse::devolverSucesso(new CategoriaDocumentoResource($categoria));
    }

    public function destroy(CategoriaDocumento $categorias_documento, EliminarCategoriaAction $accao): JsonResponse
    {
        $accao->handle($categorias_documento);

        return ApiResponse::devolverVazio();
    }
}
