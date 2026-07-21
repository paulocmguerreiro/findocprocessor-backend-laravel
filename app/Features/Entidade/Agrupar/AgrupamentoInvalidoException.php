<?php

declare(strict_types=1);

namespace App\Features\Entidade\Agrupar;

use DomainException;

/**
 * Fusão de entidades inválida — cobre as três guardas de negócio da operação de
 * agrupar duplicados. Estende `DomainException` → o handler converte em `422`,
 * evitando que uma fusão inválida surja como `500`.
 */
final class AgrupamentoInvalidoException extends DomainException
{
    public static function paraEntidadesIguais(): self
    {
        return new self('A entidade principal e a secundária têm de ser distintas.');
    }

    public static function paraEmpresaAplicacao(): self
    {
        return new self('A entidade secundária não pode ser a empresa da aplicação.');
    }

    /**
     * @param  list<string>  $colunas
     */
    public static function paraReferenciasNaoTratadas(array $colunas): self
    {
        return new self(sprintf(
            'Existem referências a entidades não tratadas pela fusão: %s.',
            implode(', ', $colunas),
        ));
    }
}
