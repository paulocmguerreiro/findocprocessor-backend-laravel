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
        /** @var array{per_page?: string, sort?: string, direction?: string} $parametrosValidados */
        $parametrosValidados = $pedido->validated();

        $porPagina = isset($parametrosValidados['per_page']) ? (int) $parametrosValidados['per_page'] : 15;
        $campoOrdenacao = CampoOrdenacaoCategorias::from($parametrosValidados['sort'] ?? CampoOrdenacaoCategorias::Nome->value);
        $direcaoOrdenacao = DirecaoOrdenacao::from($parametrosValidados['direction'] ?? DirecaoOrdenacao::Asc->value);

        $categorias = $accao->handle($porPagina, $campoOrdenacao, $direcaoOrdenacao);

        return ApiResponse::devolverPaginado(
            CategoriaDocumentoResource::collection($categorias),
        );
    }

    public function store(CriarCategoriaRequest $pedido, CriarCategoriaAction $accao): JsonResponse
    {
        $categoria = $accao->handle(CriarCategoriaDto::fromRequest($pedido));

        return ApiResponse::devolverCriado(new CategoriaDocumentoResource($categoria));
    }

    public function show(CategoriaDocumento $categorias_documento, VerCategoriaAction $accao): JsonResponse
    {
        return ApiResponse::devolverSucesso(
            new CategoriaDocumentoResource($accao->handle($categorias_documento)),
        );
    }

    public function update(ActualizarCategoriaRequest $pedido, CategoriaDocumento $categorias_documento, ActualizarCategoriaAction $accao): JsonResponse
    {
        $categoria = $accao->handle($categorias_documento, ActualizarCategoriaDto::fromRequest($pedido));

        return ApiResponse::devolverSucesso(new CategoriaDocumentoResource($categoria));
    }

    public function destroy(CategoriaDocumento $categorias_documento, EliminarCategoriaAction $accao): JsonResponse
    {
        $accao->handle($categorias_documento);

        return ApiResponse::devolverVazio();
    }
}
