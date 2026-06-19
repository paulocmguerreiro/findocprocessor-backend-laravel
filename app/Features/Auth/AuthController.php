<?php

declare(strict_types=1);

namespace App\Features\Auth;

use App\Features\Auth\CriarToken\CriarTokenAction;
use App\Features\Auth\CriarToken\CriarTokenRequest;
use App\Features\Auth\Login\LoginAction;
use App\Features\Auth\Login\LoginRequest;
use App\Features\Auth\Logout\LogoutAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    /**
     * @throws \Throwable
     */
    public function login(LoginRequest $pedido, LoginAction $accao): JsonResponse
    {
        /** @var array{email: string, password: string} $dados */
        $dados = $pedido->validated();

        $token = $accao->handle($dados['email'], $dados['password']);

        return ApiResponse::devolverSucesso(['token' => $token]);
    }

    /**
     * @throws \Throwable
     */
    public function logout(Request $pedido, LogoutAction $accao): JsonResponse
    {
        /** @var User $utilizador */
        $utilizador = $pedido->user();
        $accao->handle($utilizador);

        return ApiResponse::devolverVazio();
    }

    /**
     * @throws \Throwable
     */
    public function criarToken(CriarTokenRequest $pedido, CriarTokenAction $accao): JsonResponse
    {
        /** @var array{nome_token: string} $dados */
        $dados = $pedido->validated();

        /** @var User $utilizador */
        $utilizador = $pedido->user();

        $token = $accao->handle($utilizador, $dados['nome_token']);

        return ApiResponse::devolverSucesso(['token' => $token]);
    }
}
