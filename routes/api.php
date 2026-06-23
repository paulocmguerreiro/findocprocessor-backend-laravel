<?php

declare(strict_types=1);

use App\Features\Auth\AuthController;
use App\Features\CategoriaDocumento\CategoriaDocumentoController;
use App\Features\Entidade\EntidadeController;
use App\Features\Role\RoleController;
use App\Features\Utilizador\UtilizadorController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/tokens', [AuthController::class, 'criarToken']);

    Route::apiResource('categorias-documento', CategoriaDocumentoController::class);

    Route::apiResource('entidades', EntidadeController::class);
    Route::patch('entidades/{entidade}/empresa-mae', [EntidadeController::class, 'converterEmEmpresaMae']);

    Route::apiResource('roles', RoleController::class);
    Route::put('utilizadores/{utilizador}/role', [UtilizadorController::class, 'atribuirRole']);
});
