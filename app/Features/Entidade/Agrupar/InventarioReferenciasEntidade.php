<?php

declare(strict_types=1);

namespace App\Features\Entidade\Agrupar;

use Illuminate\Support\Facades\Schema;

/**
 * Introspecção pura do esquema: descobre, em runtime, todas as colunas FK que
 * referenciam a tabela `entidades`. Serve de guarda de futuro para a fusão de
 * entidades — se surgir uma FK nova que a allow-list de repontagem não trate, a
 * operação falha em vez de deixar referências pendentes.
 *
 * Sem interface: introspecção do esquema real, sem substituição prevista.
 */
final readonly class InventarioReferenciasEntidade
{
    private const string TABELA_REFERENCIADA = 'entidades';

    /**
     * Colunas que apontam para `entidades`, no formato `"tabela.coluna"`
     * (ex.: `documentos.id_fornecedor`), sem duplicados e ordenadas.
     *
     * @return list<string>
     */
    public function detectarColunasQueReferenciamEntidades(): array
    {
        $colunas = [];

        foreach (Schema::getTables() as $tabela) {
            $nomeTabela = $tabela['name'];

            foreach (Schema::getForeignKeys($nomeTabela) as $chaveEstrangeira) {
                if ($chaveEstrangeira['foreign_table'] !== self::TABELA_REFERENCIADA) {
                    continue;
                }

                foreach ($chaveEstrangeira['columns'] as $coluna) {
                    $colunas[] = $nomeTabela.'.'.$coluna;
                }
            }
        }

        $colunas = array_values(array_unique($colunas));
        sort($colunas);

        return $colunas;
    }
}
