<?php

declare(strict_types=1);

namespace App\Features\Documento\Transicao;

use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Support\Facades\Storage;

/**
 * Invariante de domínio: verifica se `disco_storage`/`nome_ficheiro_storage` de
 * um `Documento` reflectem a localização real do ficheiro. Quando a compensação
 * best-effort de `ExecutorTransicaoDocumento` falha, a BD pode ficar dessincronizada
 * do filesystem — esta Regra localiza o ficheiro nos discos conhecidos por hash
 * (o nome persistido pode não existir mais no disco esperado, mas mantém-se igual
 * nos outros — só o disco muda numa falha de compensação).
 *
 * Invocada por `ReconciliarFicheirosJob`. Sem `Gate::authorize()` própria — o Job
 * é a acção de sistema chamante.
 */
final readonly class RegraReconciliarLocalizacaoFicheiro
{
    public function __construct(private RegraMoverFicheiro $regraMover) {}

    public function handle(Documento $documento): ResultadoReconciliacaoFicheiro
    {
        if (Storage::disk($documento->disco_storage)->exists($documento->nome_ficheiro_storage)) {
            return new ResultadoReconciliacaoFicheiro(
                coerente: true,
                encontrado: true,
                disco: $documento->disco_storage,
                nome: $documento->nome_ficheiro_storage,
            );
        }

        foreach ($this->discosCandidatos($documento->disco_storage) as $disco) {
            $conteudo = Storage::disk($disco)->get($documento->nome_ficheiro_storage);

            if ($conteudo !== null && hash('sha256', $conteudo) === $documento->hash_sha256) {
                return new ResultadoReconciliacaoFicheiro(
                    coerente: false,
                    encontrado: true,
                    disco: $disco,
                    nome: $documento->nome_ficheiro_storage,
                );
            }
        }

        return new ResultadoReconciliacaoFicheiro(coerente: false, encontrado: false);
    }

    /**
     * Discos conhecidos (mapa de `RegraMoverFicheiro`), excluindo o disco actual.
     *
     * @return list<string>
     */
    private function discosCandidatos(string $discoActual): array
    {
        $todosDiscos = array_unique(array_map(
            $this->regraMover->discoParaEstado(...),
            EstadoDocumento::cases(),
        ));

        return array_values(array_diff($todosDiscos, [$discoActual]));
    }
}
