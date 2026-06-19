<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\CategoriaDocumentoController;
use App\Features\Entidade\EntidadeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('categorias-documento', CategoriaDocumentoController::class);

Route::apiResource('entidades', EntidadeController::class);
Route::patch('entidades/{entidade}/empresa-mae', [EntidadeController::class, 'converterEmEmpresaMae']);
