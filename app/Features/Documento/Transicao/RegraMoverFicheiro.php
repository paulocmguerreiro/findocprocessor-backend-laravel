<?php

declare(strict_types=1);

namespace App\Features\Documento\Transicao;

use App\Shared\Enums\EstadoDocumento;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Invariante de domínio: garante que o ficheiro reside no disco correspondente
 * ao estado do Documento, deixando `disco_storage`/`nome_ficheiro_storage`
 * consistentes com o novo `estado`.
 *
 * Os discos estão configurados com `throw => false`, por isso cada operação
 * verifica o valor de retorno e lança em falha. Movimento entre discos distintos
 * não é um `move()` (que opera no mesmo disco) — é `put(get())` no destino +
 * `delete()` na origem, com compensação best-effort em caso de falha parcial.
 */
final readonly class RegraMoverFicheiro
{
    /**
     * @return array{disco: string, nome: string} Disco e nome de destino, para a Action persistir.
     *
     * @throws RuntimeException
     */
    public function handle(
        string $discoOrigem,
        string $nomeOrigem,
        EstadoDocumento $novoEstado,
        ?string $nomeDestino = null,
    ): array {
        $discoDestino = $this->discoParaEstado($novoEstado);
        $nomeDestino ??= $nomeOrigem;

        // Sem alteração de localização nem de nome — nada a mover.
        if ($discoOrigem === $discoDestino && $nomeOrigem === $nomeDestino) {
            return ['disco' => $discoDestino, 'nome' => $nomeDestino];
        }

        // Mesmo disco, nome diferente — rename (ex.: correcção de Processado).
        if ($discoOrigem === $discoDestino) {
            if (! Storage::disk($discoDestino)->move($nomeOrigem, $nomeDestino)) {
                throw new RuntimeException('Falha ao renomear o ficheiro no disco.');
            }

            return ['disco' => $discoDestino, 'nome' => $nomeDestino];
        }

        // Discos distintos — copiar para o destino, depois apagar a origem.
        $conteudo = Storage::disk($discoOrigem)->get($nomeOrigem);
        if ($conteudo === null) {
            throw new RuntimeException('Ficheiro de origem inexistente.');
        }

        if (! Storage::disk($discoDestino)->put($nomeDestino, $conteudo)) {
            throw new RuntimeException('Falha ao escrever o ficheiro no disco de destino.');
        }

        if (! Storage::disk($discoOrigem)->delete($nomeOrigem)) {
            // Compensação: remover o ficheiro acabado de escrever no destino.
            Storage::disk($discoDestino)->delete($nomeDestino);

            throw new RuntimeException('Falha ao remover o ficheiro da origem.');
        }

        return ['disco' => $discoDestino, 'nome' => $nomeDestino];
    }

    /**
     * Mapa estado → disco (espelha `02-shared/estados.md`). Público — reutilizado
     * por `RegraReconciliarLocalizacaoFicheiro` para listar os discos conhecidos
     * sem duplicar o mapa.
     */
    public function discoParaEstado(EstadoDocumento $estado): string
    {
        return match ($estado) {
            EstadoDocumento::Pendente,
            EstadoDocumento::AnaliseMalware,
            EstadoDocumento::AnaliseTexto,
            EstadoDocumento::AnaliseOcr => 'entrada',
            EstadoDocumento::AnaliseIaLocal, EstadoDocumento::AnaliseCloud => 'enviado',
            EstadoDocumento::Processado => 'processado',
            EstadoDocumento::Erro => 'erro',
            EstadoDocumento::Perigoso => 'perigoso',
        };
    }
}
