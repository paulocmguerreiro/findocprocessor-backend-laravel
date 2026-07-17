<?php

declare(strict_types=1);

namespace App\Features\Documento\Pesquisa\Descarregar;

use App\Models\Documento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Acesso ao conteúdo do ficheiro de um Documento. Autoriza com a ability `view`
 * (quem vê o registo acede ao ficheiro) e devolve a referência para o Controller
 * fazer o streaming. O conteúdo nunca é embebido no `DocumentoResource`.
 */
final readonly class DescarregarDocumentoAction
{
    /**
     * @throws AuthorizationException
     * @throws NotFoundHttpException
     */
    public function handle(Documento $documento): FicheiroDocumentoDto
    {
        Gate::authorize('view', $documento);

        if (! Storage::disk($documento->disco_storage)->exists($documento->nome_ficheiro_storage)) {
            throw new NotFoundHttpException('Ficheiro do documento não encontrado.');
        }

        return new FicheiroDocumentoDto(
            disco: $documento->disco_storage,
            nomeStorage: $documento->nome_ficheiro_storage,
            nomeOriginal: $documento->nome_ficheiro_original,
        );
    }
}
