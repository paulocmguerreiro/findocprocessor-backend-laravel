<?php

declare(strict_types=1);

namespace App\Features\Utilizador;

use App\Features\Utilizador\Actualizar\ActualizarUtilizadorAction;
use App\Features\Utilizador\Actualizar\ActualizarUtilizadorDto;
use App\Features\Utilizador\Actualizar\ActualizarUtilizadorRequest;
use App\Features\Utilizador\Anonimizar\AnonimizarUtilizadorAction;
use App\Features\Utilizador\Anonimizar\AnonimizarUtilizadorRequest;
use App\Features\Utilizador\AtribuirRole\AtribuirRoleAction;
use App\Features\Utilizador\AtribuirRole\AtribuirRoleRequest;
use App\Features\Utilizador\Criar\CriarUtilizadorAction;
use App\Features\Utilizador\Criar\CriarUtilizadorDto;
use App\Features\Utilizador\Criar\CriarUtilizadorRequest;
use App\Features\Utilizador\Eliminar\EliminarUtilizadorAction;
use App\Features\Utilizador\Eliminar\EliminarUtilizadorRequest;
use App\Features\Utilizador\Listar\CampoOrdenacaoUtilizadores;
use App\Features\Utilizador\Listar\ListarUtilizadoresAction;
use App\Features\Utilizador\Listar\ListarUtilizadoresRequest;
use App\Features\Utilizador\Restaurar\RestaurarUtilizadorAction;
use App\Features\Utilizador\Restaurar\RestaurarUtilizadorRequest;
use App\Features\Utilizador\Ver\VerUtilizadorAction;
use App\Features\Utilizador\Ver\VerUtilizadorRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Enums\FiltroEstadoRegisto;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UtilizadorController extends Controller
{
    public function index(ListarUtilizadoresRequest $pedido, ListarUtilizadoresAction $accao): JsonResponse
    {
        /** @var array{per_page?: string, sort?: string, direction?: string, estado?: string} $parametrosValidados */
        $parametrosValidados = $pedido->validated();

        $porPagina = isset($parametrosValidados['per_page']) ? (int) $parametrosValidados['per_page'] : 15;
        $campoOrdenacao = CampoOrdenacaoUtilizadores::from($parametrosValidados['sort'] ?? CampoOrdenacaoUtilizadores::Nome->value);
        $direcaoOrdenacao = DirecaoOrdenacao::from($parametrosValidados['direction'] ?? DirecaoOrdenacao::Asc->value);
        $filtroEstado = FiltroEstadoRegisto::from($parametrosValidados['estado'] ?? FiltroEstadoRegisto::SomenteAtivos->value);

        $utilizadores = $accao->handle($porPagina, $campoOrdenacao, $direcaoOrdenacao, $filtroEstado);

        return ApiResponse::devolverPaginado(
            UtilizadorResource::collection($utilizadores),
        );
    }

    public function store(CriarUtilizadorRequest $pedido, CriarUtilizadorAction $accao): JsonResponse
    {
        $utilizador = $accao->handle(CriarUtilizadorDto::fromRequest($pedido));

        return ApiResponse::devolverCriado(new UtilizadorResource($utilizador));
    }

    public function show(VerUtilizadorRequest $pedido, User $utilizador, VerUtilizadorAction $accao): JsonResponse
    {
        return ApiResponse::devolverSucesso(
            new UtilizadorResource($accao->handle($utilizador)),
        );
    }

    public function update(ActualizarUtilizadorRequest $pedido, User $utilizador, ActualizarUtilizadorAction $accao): JsonResponse
    {
        $utilizador = $accao->handle($utilizador, ActualizarUtilizadorDto::fromRequest($pedido));

        return ApiResponse::devolverSucesso(new UtilizadorResource($utilizador));
    }

    public function destroy(EliminarUtilizadorRequest $pedido, User $utilizador, EliminarUtilizadorAction $accao): JsonResponse
    {
        $accao->handle($utilizador);

        return ApiResponse::devolverVazio();
    }

    public function atribuirRole(AtribuirRoleRequest $pedido, User $utilizador, AtribuirRoleAction $accao): JsonResponse
    {
        /** @var array{role: string} $dadosValidados */
        $dadosValidados = $pedido->validated();

        $accao->handle($utilizador, $dadosValidados['role']);

        return ApiResponse::devolverVazio();
    }

    public function restaurar(RestaurarUtilizadorRequest $pedido, User $utilizador, RestaurarUtilizadorAction $accao): JsonResponse
    {
        return ApiResponse::devolverSucesso(
            new UtilizadorResource($accao->handle($utilizador)),
        );
    }

    public function anonimizar(AnonimizarUtilizadorRequest $pedido, User $utilizador, AnonimizarUtilizadorAction $accao): JsonResponse
    {
        $accao->handle($utilizador);

        return ApiResponse::devolverVazio();
    }
}
