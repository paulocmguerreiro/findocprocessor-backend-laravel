<?php

declare(strict_types=1);

namespace App\Features\TipoDocumento;

use App\Features\TipoDocumento\Actualizar\ActualizarTipoDocumentoAction;
use App\Features\TipoDocumento\Actualizar\ActualizarTipoDocumentoDto;
use App\Features\TipoDocumento\Actualizar\ActualizarTipoDocumentoRequest;
use App\Features\TipoDocumento\Criar\CriarTipoDocumentoAction;
use App\Features\TipoDocumento\Criar\CriarTipoDocumentoDto;
use App\Features\TipoDocumento\Criar\CriarTipoDocumentoRequest;
use App\Features\TipoDocumento\Eliminar\EliminarTipoDocumentoAction;
use App\Features\TipoDocumento\Eliminar\EliminarTipoDocumentoRequest;
use App\Features\TipoDocumento\Listar\CampoOrdenacaoTiposDocumento;
use App\Features\TipoDocumento\Listar\ListarTiposDocumentoAction;
use App\Features\TipoDocumento\Listar\ListarTiposDocumentoRequest;
use App\Features\TipoDocumento\Ver\VerTipoDocumentoAction;
use App\Features\TipoDocumento\Ver\VerTipoDocumentoRequest;
use App\Http\Controllers\Controller;
use App\Models\TipoDocumento;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TipoDocumentoController extends Controller
{
    public function index(ListarTiposDocumentoRequest $pedido, ListarTiposDocumentoAction $accao): JsonResponse
    {
        /** @var array{per_page?: string, sort?: string, direction?: string, id_categoria?: string} $parametrosValidados */
        $parametrosValidados = $pedido->validated();

        $porPagina = isset($parametrosValidados['per_page']) ? (int) $parametrosValidados['per_page'] : 15;
        $campoOrdenacao = CampoOrdenacaoTiposDocumento::from($parametrosValidados['sort'] ?? CampoOrdenacaoTiposDocumento::Nome->value);
        $direcaoOrdenacao = DirecaoOrdenacao::from($parametrosValidados['direction'] ?? DirecaoOrdenacao::Asc->value);
        $idCategoria = $parametrosValidados['id_categoria'] ?? null;

        $tiposDocumento = $accao->handle($porPagina, $campoOrdenacao, $direcaoOrdenacao, $idCategoria);

        return ApiResponse::devolverPaginado(
            TipoDocumentoResource::collection($tiposDocumento),
        );
    }

    public function store(CriarTipoDocumentoRequest $pedido, CriarTipoDocumentoAction $accao): JsonResponse
    {
        $tipoDocumento = $accao->handle(CriarTipoDocumentoDto::fromRequest($pedido));

        return ApiResponse::devolverCriado(new TipoDocumentoResource($tipoDocumento));
    }

    public function show(VerTipoDocumentoRequest $pedido, TipoDocumento $tipos_documento, VerTipoDocumentoAction $accao): JsonResponse
    {
        return ApiResponse::devolverSucesso(
            new TipoDocumentoResource($accao->handle($tipos_documento)),
        );
    }

    public function update(ActualizarTipoDocumentoRequest $pedido, TipoDocumento $tipos_documento, ActualizarTipoDocumentoAction $accao): JsonResponse
    {
        $tipoDocumento = $accao->handle($tipos_documento, ActualizarTipoDocumentoDto::fromRequest($pedido));

        return ApiResponse::devolverSucesso(new TipoDocumentoResource($tipoDocumento));
    }

    public function destroy(EliminarTipoDocumentoRequest $pedido, TipoDocumento $tipos_documento, EliminarTipoDocumentoAction $accao): JsonResponse
    {
        $accao->handle($tipos_documento);

        return ApiResponse::devolverVazio();
    }
}
