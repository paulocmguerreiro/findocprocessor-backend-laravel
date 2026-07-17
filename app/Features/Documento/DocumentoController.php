<?php

declare(strict_types=1);

namespace App\Features\Documento;

use App\Features\Documento\Corrigir\CorrigirDocumentoAction;
use App\Features\Documento\Corrigir\CorrigirDocumentoDto;
use App\Features\Documento\Corrigir\CorrigirDocumentoRequest;
use App\Features\Documento\Criar\CriarDocumentoManualRequest;
use App\Features\Documento\Criar\RegistarDocumentoManualAction;
use App\Features\Documento\Criar\RegistarDocumentoManualDto;
use App\Features\Documento\Eliminar\EliminarDocumentoAction;
use App\Features\Documento\Eliminar\EliminarDocumentoRequest;
use App\Features\Documento\Operacoes\Reprocessar\ReprocessarDocumentoAction;
use App\Features\Documento\Operacoes\Reprocessar\ReprocessarDocumentoDto;
use App\Features\Documento\Operacoes\Reprocessar\ReprocessarDocumentoRequest;
use App\Features\Documento\Pesquisa\Descarregar\DescarregarDocumentoAction;
use App\Features\Documento\Pesquisa\Descarregar\DescarregarDocumentoRequest;
use App\Features\Documento\Pesquisa\Listar\CampoOrdenacaoDocumentos;
use App\Features\Documento\Pesquisa\Listar\ListarDocumentosAction;
use App\Features\Documento\Pesquisa\Listar\ListarDocumentosRequest;
use App\Features\Documento\Pesquisa\Ver\VerDocumentoAction;
use App\Features\Documento\Pesquisa\Ver\VerDocumentoRequest;
use App\Features\Documento\RecepcaoUpload\ReceberUploadDocumentoAction;
use App\Features\Documento\RecepcaoUpload\ReceberUploadDocumentoDto;
use App\Features\Documento\RecepcaoUpload\ReceberUploadDocumentoRequest;
use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentoController extends Controller
{
    public function index(ListarDocumentosRequest $pedido, ListarDocumentosAction $accao): JsonResponse
    {
        /** @var array{per_page?: string, sort?: string, direction?: string, estado?: string} $parametros */
        $parametros = $pedido->validated();

        $porPagina = isset($parametros['per_page']) ? (int) $parametros['per_page'] : 15;
        $campoOrdenacao = CampoOrdenacaoDocumentos::from($parametros['sort'] ?? CampoOrdenacaoDocumentos::CriadoEm->value);
        $direcaoOrdenacao = DirecaoOrdenacao::from($parametros['direction'] ?? DirecaoOrdenacao::Desc->value);
        $estado = isset($parametros['estado']) ? EstadoDocumento::from($parametros['estado']) : null;

        return ApiResponse::devolverPaginado(
            DocumentoResource::collection($accao->handle($porPagina, $campoOrdenacao, $direcaoOrdenacao, $estado)),
        );
    }

    public function store(CriarDocumentoManualRequest $pedido, RegistarDocumentoManualAction $accao): JsonResponse
    {
        $documento = $accao->handle(RegistarDocumentoManualDto::fromRequest($pedido));

        return ApiResponse::devolverCriado(new DocumentoResource($documento->load(['fornecedor', 'cliente', 'categoria'])));
    }

    public function upload(ReceberUploadDocumentoRequest $pedido, ReceberUploadDocumentoAction $accao): JsonResponse
    {
        $documento = $accao->handle(ReceberUploadDocumentoDto::fromRequest($pedido));

        return ApiResponse::devolverCriado(new DocumentoResource($documento));
    }

    public function show(VerDocumentoRequest $pedido, Documento $documento, VerDocumentoAction $accao): JsonResponse
    {
        $documento = $accao->handle($documento)->load(['fornecedor', 'cliente', 'categoria']);

        return ApiResponse::devolverSucesso(new DocumentoResource($documento));
    }

    public function descarregar(DescarregarDocumentoRequest $pedido, Documento $documento, DescarregarDocumentoAction $accao): StreamedResponse
    {
        $ficheiro = $accao->handle($documento);

        return Storage::disk($ficheiro->disco)->download($ficheiro->nomeStorage, $ficheiro->nomeOriginal);
    }

    public function update(CorrigirDocumentoRequest $pedido, Documento $documento, CorrigirDocumentoAction $accao): JsonResponse
    {
        $documento = $accao->handle($documento, CorrigirDocumentoDto::fromRequest($pedido))->load(['fornecedor', 'cliente', 'categoria']);

        return ApiResponse::devolverSucesso(new DocumentoResource($documento));
    }

    public function reprocessar(ReprocessarDocumentoRequest $pedido, Documento $documento, ReprocessarDocumentoAction $accao): JsonResponse
    {
        $documento = $accao->handle($documento, ReprocessarDocumentoDto::fromRequest($pedido));

        return ApiResponse::devolverSucesso(new DocumentoResource($documento));
    }

    public function destroy(EliminarDocumentoRequest $pedido, Documento $documento, EliminarDocumentoAction $accao): JsonResponse
    {
        $accao->handle($documento);

        return ApiResponse::devolverVazio();
    }
}
