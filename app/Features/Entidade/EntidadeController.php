<?php

declare(strict_types=1);

namespace App\Features\Entidade;

use App\Features\Entidade\Actualizar\ActualizarEntidadeAction;
use App\Features\Entidade\Actualizar\ActualizarEntidadeDto;
use App\Features\Entidade\Actualizar\ActualizarEntidadeRequest;
use App\Features\Entidade\Criar\CriarEntidadeAction;
use App\Features\Entidade\Criar\CriarEntidadeDto;
use App\Features\Entidade\Criar\CriarEntidadeRequest;
use App\Features\Entidade\Eliminar\EliminarEntidadeAction;
use App\Features\Entidade\Eliminar\EliminarEntidadeRequest;
use App\Features\Entidade\EmpresaMae\ConverterEmEmpresaMaeAction;
use App\Features\Entidade\EmpresaMae\ConverterEmEmpresaMaeRequest;
use App\Features\Entidade\Listar\CampoOrdenacaoEntidades;
use App\Features\Entidade\Listar\ListarEntidadesAction;
use App\Features\Entidade\Listar\ListarEntidadesRequest;
use App\Features\Entidade\Ver\VerEntidadeAction;
use App\Features\Entidade\Ver\VerEntidadeRequest;
use App\Http\Controllers\Controller;
use App\Models\Entidade;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Enums\FiltroEstadoRegisto;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class EntidadeController extends Controller
{
    public function index(ListarEntidadesRequest $pedido, ListarEntidadesAction $accao): JsonResponse
    {
        /** @var array{per_page?: string, sort?: string, direction?: string, estado?: string} $parametrosValidados */
        $parametrosValidados = $pedido->validated();

        $porPagina = isset($parametrosValidados['per_page']) ? (int) $parametrosValidados['per_page'] : 15;
        $campoOrdenacao = CampoOrdenacaoEntidades::from($parametrosValidados['sort'] ?? CampoOrdenacaoEntidades::Nome->value);
        $direcaoOrdenacao = DirecaoOrdenacao::from($parametrosValidados['direction'] ?? DirecaoOrdenacao::Asc->value);
        $filtroEstado = FiltroEstadoRegisto::from($parametrosValidados['estado'] ?? FiltroEstadoRegisto::SomenteAtivos->value);

        return ApiResponse::devolverPaginado(
            EntidadeResource::collection($accao->handle($porPagina, $campoOrdenacao, $direcaoOrdenacao, $filtroEstado)),
        );
    }

    public function store(CriarEntidadeRequest $pedido, CriarEntidadeAction $accao): JsonResponse
    {
        $entidade = $accao->handle(CriarEntidadeDto::fromRequest($pedido));

        return ApiResponse::devolverCriado(new EntidadeResource($entidade));
    }

    public function show(VerEntidadeRequest $pedido, Entidade $entidade, VerEntidadeAction $accao): JsonResponse
    {
        return ApiResponse::devolverSucesso(new EntidadeResource($accao->handle($entidade)));
    }

    public function update(ActualizarEntidadeRequest $pedido, Entidade $entidade, ActualizarEntidadeAction $accao): JsonResponse
    {
        $entidade = $accao->handle($entidade, ActualizarEntidadeDto::fromRequest($pedido));

        return ApiResponse::devolverSucesso(new EntidadeResource($entidade));
    }

    public function destroy(EliminarEntidadeRequest $pedido, Entidade $entidade, EliminarEntidadeAction $accao): JsonResponse
    {
        $accao->handle($entidade);

        return ApiResponse::devolverVazio();
    }

    public function converterEmEmpresaMae(ConverterEmEmpresaMaeRequest $pedido, Entidade $entidade, ConverterEmEmpresaMaeAction $accao): JsonResponse
    {
        $entidade = $accao->handle($entidade);

        return ApiResponse::devolverSucesso(new EntidadeResource($entidade));
    }
}
