<?php

declare(strict_types=1);

use App\Features\Auth\AuthController;
use App\Features\CategoriaDocumento\CategoriaDocumentoController;
use App\Features\Documento\DocumentoController;
use App\Features\Entidade\EntidadeController;
use App\Features\Role\RoleController;
use App\Features\Utilizador\UtilizadorController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/tokens', [AuthController::class, 'criarToken']);

    Route::apiResource('categorias-documento', CategoriaDocumentoController::class);

    Route::apiResource('entidades', EntidadeController::class)
        ->withTrashed(['show', 'update', 'destroy']);
    Route::patch('entidades/{entidade}/restaurar', [EntidadeController::class, 'restaurar'])
        ->withTrashed();
    Route::patch('entidades/{entidade}/empresa-mae', [EntidadeController::class, 'converterEmEmpresaMae']);

    Route::apiResource('roles', RoleController::class);

    Route::apiResource('utilizadores', UtilizadorController::class)
        ->parameters(['utilizadores' => 'utilizador'])
        ->withTrashed(['show', 'update', 'destroy']);
    Route::put('utilizadores/{utilizador}/role', [UtilizadorController::class, 'atribuirRole']);
    Route::patch('utilizadores/{utilizador}/restaurar', [UtilizadorController::class, 'restaurar'])
        ->withTrashed();
    Route::post('utilizadores/{utilizador}/anonimizar', [UtilizadorController::class, 'anonimizar']);

    Route::post('documentos/upload', [DocumentoController::class, 'upload']);
    Route::apiResource('documentos', DocumentoController::class);
    Route::get('documentos/{documento}/ficheiro', [DocumentoController::class, 'descarregar']);
    Route::post('documentos/{documento}/reprocessar', [DocumentoController::class, 'reprocessar']);
});
