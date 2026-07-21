<?php

declare(strict_types=1);

namespace App\Features\Entidade\Agrupar;

/**
 * Contrato da introspecção do esquema que descobre as colunas FK que referenciam
 * `entidades`. Existe como interface para permitir substituição em testes (simular
 * uma FK nova não tratada sem manipular o esquema real — incompatível com o setup
 * de testes em paralelo sobre base de dados partilhada).
 */
interface InventarioReferenciasEntidadeInterface
{
    /**
     * Colunas que apontam para `entidades`, no formato `"tabela.coluna"`.
     *
     * @return list<string>
     */
    public function detectarColunasQueReferenciamEntidades(): array;
}
