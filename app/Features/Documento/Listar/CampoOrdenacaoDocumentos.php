<?php

declare(strict_types=1);

namespace App\Features\Documento\Listar;

enum CampoOrdenacaoDocumentos: string
{
    case DataDocumento = 'data_documento';
    case CriadoEm = 'created_at';
}
