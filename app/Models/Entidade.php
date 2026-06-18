<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\EntidadePolicy;
use Database\Factories\EntidadeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read string $nome
 * @property-read string $nif
 * @property-read bool   $e_cliente
 * @property-read bool   $e_fornecedor
 * @property-read bool   $e_empresa_aplicacao
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[Table('entidades')]
#[Fillable(['nome', 'nif', 'e_cliente', 'e_fornecedor', 'e_empresa_aplicacao'])]
#[UsePolicy(EntidadePolicy::class)]
class Entidade extends Model
{
    /** @use HasFactory<EntidadeFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array{e_cliente: string, e_fornecedor: string, e_empresa_aplicacao: string}
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'e_cliente' => 'boolean',
            'e_fornecedor' => 'boolean',
            'e_empresa_aplicacao' => 'boolean',
        ];
    }

    public function scopeWhereCliente(Builder $query): void
    {
        $query->where('e_cliente', true);
    }

    public function scopeWhereFornecedor(Builder $query): void
    {
        $query->where('e_fornecedor', true);
    }

    public function scopeWhereEmpresaAplicacao(Builder $query): void
    {
        $query->where('e_empresa_aplicacao', true);
    }
}
