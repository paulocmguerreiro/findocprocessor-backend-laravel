<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\CategoriaDocumentoController;
use Illuminate\Support\Facades\Route;

Route::apiResource('categorias-documento', CategoriaDocumentoController::class);
