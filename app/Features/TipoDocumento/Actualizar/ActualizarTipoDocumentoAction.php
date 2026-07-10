<?php

declare(strict_types=1);

namespace App\Features\TipoDocumento\Actualizar;

use App\Models\TipoDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class ActualizarTipoDocumentoAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws ModelNotFoundException<TipoDocumento>
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(TipoDocumento|string $idTipoDocumento, ActualizarTipoDocumentoDto $dados): TipoDocumento
    {
        /** @var TipoDocumento $tipoDocumento */
        $tipoDocumento = is_string($idTipoDocumento)
            ? TipoDocumento::findOrFail($idTipoDocumento)
            : $idTipoDocumento;

        Gate::authorize('update', $tipoDocumento);

        Log::info('tipo_documento.actualizar.inicio', ['id_utilizador' => Auth::id()]);

        $tipoDocumento = DB::transaction(function () use ($tipoDocumento, $dados): TipoDocumento {
            $tipoDocumento->fill([
                'nome' => $dados->nome,
                'descricao' => $dados->descricao,
                'id_categoria' => $dados->idCategoria,
                'posicao_empresa_mae' => $dados->posicaoEmpresaMae,
                'espera_data_documento' => $dados->esperaDataDocumento,
                'espera_fornecedor' => $dados->esperaFornecedor,
                'espera_cliente' => $dados->esperaCliente,
                'espera_valor' => $dados->esperaValor,
            ])->save();

            $tipoDocumento->refresh();
            $tipoDocumento->load('categoria');

            $this->cache->invalidarCache(TagCache::TiposDocumento);

            return $tipoDocumento;
        });

        Log::info('tipo_documento.actualizar.fim', ['id_utilizador' => Auth::id(), 'id_tipo_documento' => $tipoDocumento->id]);

        return $tipoDocumento;
    }
}
