<?php

declare(strict_types=1);

namespace App\Features\Utilizador;

use App\Features\Utilizador\AtribuirRole\AtribuirRoleAction;
use App\Features\Utilizador\AtribuirRole\AtribuirRoleRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UtilizadorController extends Controller
{
    public function atribuirRole(AtribuirRoleRequest $pedido, User $utilizador, AtribuirRoleAction $accao): JsonResponse
    {
        /** @var array{role: string} $dadosValidados */
        $dadosValidados = $pedido->validated();

        $accao->handle($utilizador, $dadosValidados['role']);

        return ApiResponse::devolverVazio();
    }
}
