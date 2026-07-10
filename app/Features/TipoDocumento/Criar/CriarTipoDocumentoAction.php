<?php

declare(strict_types=1);

namespace App\Features\TipoDocumento\Criar;

use App\Models\TipoDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final readonly class CriarTipoDocumentoAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(CriarTipoDocumentoDto $dados): TipoDocumento
    {
        Gate::authorize('create', TipoDocumento::class);

        Log::info('tipo_documento.criar.inicio', ['id_utilizador' => Auth::id()]);

        $tipoDocumento = DB::transaction(function () use ($dados): TipoDocumento {
            $tipoDocumento = TipoDocumento::create([
                'nome' => $dados->nome,
                'descricao' => $dados->descricao,
                'id_categoria' => $dados->idCategoria,
                'posicao_empresa_mae' => $dados->posicaoEmpresaMae,
                'espera_data_documento' => $dados->esperaDataDocumento,
                'espera_fornecedor' => $dados->esperaFornecedor,
                'espera_cliente' => $dados->esperaCliente,
                'espera_valor' => $dados->esperaValor,
            ])->load('categoria');

            $this->cache->invalidarCache(TagCache::TiposDocumento);

            return $tipoDocumento;
        });

        Log::info('tipo_documento.criar.fim', ['id_utilizador' => Auth::id(), 'id_tipo_documento' => $tipoDocumento->id]);

        return $tipoDocumento;
    }
}
