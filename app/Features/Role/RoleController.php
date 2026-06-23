<?php

declare(strict_types=1);

namespace App\Features\Role;

use App\Features\Role\Actualizar\ActualizarRoleAction;
use App\Features\Role\Actualizar\ActualizarRoleDto;
use App\Features\Role\Actualizar\ActualizarRoleRequest;
use App\Features\Role\Criar\CriarRoleAction;
use App\Features\Role\Criar\CriarRoleDto;
use App\Features\Role\Criar\CriarRoleRequest;
use App\Features\Role\Eliminar\EliminarRoleAction;
use App\Features\Role\Eliminar\EliminarRoleRequest;
use App\Features\Role\Listar\CampoOrdenacaoRoles;
use App\Features\Role\Listar\ListarRolesAction;
use App\Features\Role\Listar\ListarRolesRequest;
use App\Features\Role\Ver\VerRoleAction;
use App\Features\Role\Ver\VerRoleRequest;
use App\Http\Controllers\Controller;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

final class RoleController extends Controller
{
    public function index(ListarRolesRequest $pedido, ListarRolesAction $accao): JsonResponse
    {
        /** @var array{per_page?: string, sort?: string, direction?: string} $parametrosValidados */
        $parametrosValidados = $pedido->validated();

        $porPagina = isset($parametrosValidados['per_page']) ? (int) $parametrosValidados['per_page'] : 15;
        $campoOrdenacao = CampoOrdenacaoRoles::from($parametrosValidados['sort'] ?? CampoOrdenacaoRoles::Nome->value);
        $direcaoOrdenacao = DirecaoOrdenacao::from($parametrosValidados['direction'] ?? DirecaoOrdenacao::Asc->value);

        return ApiResponse::devolverPaginado(
            RoleResource::collection($accao->handle($porPagina, $campoOrdenacao, $direcaoOrdenacao)),
        );
    }

    public function store(CriarRoleRequest $pedido, CriarRoleAction $accao): JsonResponse
    {
        $role = $accao->handle(CriarRoleDto::fromRequest($pedido));

        return ApiResponse::devolverCriado(new RoleResource($role));
    }

    public function show(VerRoleRequest $pedido, Role $role, VerRoleAction $accao): JsonResponse
    {
        return ApiResponse::devolverSucesso(new RoleResource($accao->handle($role)));
    }

    public function update(ActualizarRoleRequest $pedido, Role $role, ActualizarRoleAction $accao): JsonResponse
    {
        $role = $accao->handle($role, ActualizarRoleDto::fromRequest($pedido));

        return ApiResponse::devolverSucesso(new RoleResource($role));
    }

    public function destroy(EliminarRoleRequest $pedido, Role $role, EliminarRoleAction $accao): JsonResponse
    {
        $accao->handle($role);

        return ApiResponse::devolverVazio();
    }
}
