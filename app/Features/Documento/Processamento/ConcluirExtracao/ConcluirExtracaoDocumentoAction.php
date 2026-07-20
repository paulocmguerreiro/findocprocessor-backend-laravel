<?php

declare(strict_types=1);

namespace App\Features\Documento\Processamento\ConcluirExtracao;

use App\Features\Documento\Operacoes\TransicoesEstado\MarcarErroDocumentoAction;
use App\Features\Documento\Operacoes\TransicoesEstado\MarcarErroDocumentoDto;
use App\Features\Documento\Operacoes\TransicoesEstado\TransicionarProcessadoDocumentoAction;
use App\Features\Documento\Operacoes\TransicoesEstado\TransicionarProcessadoDocumentoDto;
use App\Infrastructure\AI\ResultadoExtracaoIA;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Conclusão da extracção quando o veredicto da IA é **completo** (RF-10/RF-11),
 * partilhada por `AnaliseIaLocal` e `AnaliseCloud` (mesma lógica): reconcilia as
 * entidades por lado (T4) e transiciona o documento para `Processado`. Empresa
 * mãe em falta (RN-06) → `Erro` directo (config operacional em falta, não conta
 * tentativa); veredicto completo sem `TipoDocumento` resolvido → `Erro`.
 *
 * `TransicionarProcessadoDocumentoAction` é o único passo do pipeline com
 * `Gate::authorize('update')` e o pipeline corre sem sessão HTTP — por isso a
 * transição é executada **autenticada como o responsável do documento** (o autor
 * do upload, `id_responsavel`), com restauro do utilizador anterior. Acção de
 * sistema — sem `Gate` próprio.
 */
final readonly class ConcluirExtracaoDocumentoAction
{
    public function __construct(
        private RegraReconciliarEntidadesDocumento $reconciliar,
        private TransicionarProcessadoDocumentoAction $transicionarProcessado,
        private MarcarErroDocumentoAction $marcarErro,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(Documento $documento, ResultadoExtracaoIA $resultado): Documento
    {
        // Um veredicto `completo` garante sempre o `TipoDocumento` (contrato de
        // `ResultadoExtracaoIA::completo()`); o `?? throw` só estreita o tipo para o Larastan.
        $tipoDocumento = $resultado->tipoDocumento ?? throw new LogicException('ResultadoExtracaoIA completo sem TipoDocumento resolvido.');

        try {
            $reconciliado = $this->reconciliar->handle($resultado, $tipoDocumento);
        } catch (ModelNotFoundException) {
            return $this->marcarErro->handle($documento, new MarcarErroDocumentoDto('empresa mãe não configurada'));
        }

        $dados = new TransicionarProcessadoDocumentoDto(
            idFornecedor: $reconciliado->idFornecedor,
            idCliente: $reconciliado->idCliente,
            idCategoria: $reconciliado->idCategoria,
            valor: $resultado->valor,
            dataDocumento: $resultado->dataDocumento,
            nomeFornecedorExtraido: $reconciliado->nomeFornecedorParaNome,
        );

        return $this->comoResponsavel(
            $documento,
            fn (): Documento => $this->transicionarProcessado->handle($documento, $dados),
        );
    }

    /**
     * @param  callable(): Documento  $operacao
     *
     * @throws \Throwable
     */
    private function comoResponsavel(Documento $documento, callable $operacao): Documento
    {
        $utilizadorAnterior = Auth::user();
        $responsavel = $documento->id_responsavel !== null ? User::find($documento->id_responsavel) : null;

        if ($responsavel instanceof User) {
            Auth::login($responsavel);
        }

        try {
            return $operacao();
        } finally {
            if ($utilizadorAnterior instanceof Authenticatable) {
                Auth::login($utilizadorAnterior);
            } else {
                Auth::logout();
            }
        }
    }
}
