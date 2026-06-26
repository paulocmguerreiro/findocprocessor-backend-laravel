<?php

declare(strict_types=1);

namespace App\Shared\Cache;

enum TagCache: string
{
    case Entidades = 'entidades';
    case CategoriasDocumento = 'categorias_documento';
    case Roles = 'roles';
    case Documentos = 'documentos';
}
