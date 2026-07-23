<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CategoriaDocumento;
use App\Models\Entidade;
use App\Models\TipoDocumento;
use App\Shared\Enums\PosicaoEmpresaMae;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Database\Seeder;

/**
 * Dados de domínio para a simulação/testes E2E do pipeline de extração:
 * empresa mãe (singleton resolvido por RegraReconciliarEntidadesDocumento),
 * categorias e os TipoDocumento que o LLM pode classificar (o veredicto da IA
 * faz TipoDocumento::where('nome', ...)->first()).
 *
 * Cobre os três lados da reconciliação de entidades:
 *  - Fatura de Compra   → empresa mãe é o CLIENTE; extrai só o fornecedor.
 *  - Fatura de Venda    → empresa mãe é o FORNECEDOR; extrai só o cliente.
 *  - Fatura de Serviços → empresa mãe é o CLIENTE; extrai fornecedor E cliente.
 *
 * Idempotente — pode correr várias vezes sem duplicar.
 */
final class SimulacaoPipelineSeeder extends Seeder
{
    public function run(): void
    {
        $empresaMae = Entidade::query()->updateOrCreate(
            ['nif' => '501234567'],
            [
                'nome' => 'FinDoc Serviços, Lda.',
                'e_empresa_aplicacao' => true,
                'e_cliente' => false,
                'e_fornecedor' => false,
            ],
        );

        $compras = CategoriaDocumento::query()->updateOrCreate(
            ['slug' => 'compras-e-servicos'],
            ['nome' => 'Compras e Serviços', 'tipo_movimento' => TipoMovimento::Debito],
        );

        $vendas = CategoriaDocumento::query()->updateOrCreate(
            ['slug' => 'vendas'],
            ['nome' => 'Vendas', 'tipo_movimento' => TipoMovimento::Credito],
        );

        // Descrições NEUTRAS — por natureza do documento, sem nomear a empresa mãe nem
        // fixar o seu papel (extração role-neutral, ver PromptBuilder). A direcção
        // (compra vs venda) é resolvida por NIF em código, nunca sugerida ao modelo;
        // `posicao_empresa_mae` marca o sentido do tipo, usado pela correcção de tipo
        // em RegraReconciliarEntidadesDocumento.
        $tipos = [
            [
                'nome' => 'Fatura de Compra',
                'descricao' => 'Fatura de aquisição de BENS/mercadorias (produtos físicos: material, equipamento, consumíveis).',
                'id_categoria' => $compras->id,
                'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente,
            ],
            [
                'nome' => 'Fatura de Venda',
                'descricao' => 'Fatura de venda de bens ou serviços emitida a um cliente.',
                'id_categoria' => $vendas->id,
                'posicao_empresa_mae' => PosicaoEmpresaMae::Fornecedor,
            ],
            [
                'nome' => 'Fatura de Serviços',
                'descricao' => 'Fatura de prestação de SERVIÇOS (avenças, subscrições, consultoria, suporte, licenças).',
                'id_categoria' => $compras->id,
                'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente,
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoDocumento::query()->updateOrCreate(
                ['nome' => $tipo['nome']],
                [
                    'descricao' => $tipo['descricao'],
                    'id_categoria' => $tipo['id_categoria'],
                    'posicao_empresa_mae' => $tipo['posicao_empresa_mae'],
                    'espera_data_documento' => true,
                    'espera_fornecedor' => true,
                    'espera_cliente' => true,
                    'espera_valor' => true,
                ],
            );
        }

        $this->command?->info(sprintf(
            'Simulação pronta: empresa mãe [%s], 2 categorias, %d tipos de documento.',
            $empresaMae->nome,
            count($tipos),
        ));
    }
}
