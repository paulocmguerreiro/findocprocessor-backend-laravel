<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Criar;

use App\Models\CategoriaDocumento;

final class CriarCategoriaAction
{
    public function handle(CriarCategoriaDto $dados): CategoriaDocumento
    {
        return CategoriaDocumento::create([
            'nome' => $dados->nome,
            'slug' => $dados->slug,
            'tipo_movimento' => $dados->tipo_movimento,
        ]);
    }
}
