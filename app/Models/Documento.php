<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use App\Policies\DocumentoPolicy;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\States\DocumentoAnaliseCloud;
use App\Shared\States\DocumentoAnaliseIaLocal;
use App\Shared\States\DocumentoAnaliseMalware;
use App\Shared\States\DocumentoAnaliseOcr;
use App\Shared\States\DocumentoAnaliseTexto;
use App\Shared\States\DocumentoErro;
use App\Shared\States\DocumentoPendente;
use App\Shared\States\DocumentoPerigoso;
use App\Shared\States\DocumentoProcessado;
use App\Shared\States\EstadoDocumentoInterface;
use Database\Factories\DocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read EstadoDocumento $estado
 * @property-read ?int $id_responsavel
 * @property-read ?string $id_fornecedor
 * @property-read ?string $id_cliente
 * @property-read ?string $id_categoria
 * @property-read ?string $valor
 * @property-read ?Carbon $data_documento
 * @property-read string $nome_ficheiro_original
 * @property-read string $disco_storage
 * @property-read string $nome_ficheiro_storage
 * @property-read string $hash_sha256
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read ?Entidade $fornecedor
 * @property-read ?Entidade $cliente
 * @property-read ?CategoriaDocumento $categoria
 * @property-read ?User $responsavel
 * @property-read Collection<int, EtapaDocumento> $historico
 * @property-read ?ExtracaoDocumento $extracao
 */
#[Table('documentos')]
#[Fillable([
    'estado', 'id_responsavel', 'id_fornecedor', 'id_cliente', 'id_categoria', 'valor',
    'data_documento', 'nome_ficheiro_original', 'disco_storage',
    'nome_ficheiro_storage', 'hash_sha256',
])]
#[UsePolicy(DocumentoPolicy::class)]
class Documento extends Model
{
    /** @use HasFactory<DocumentoFactory> */
    use HasFactory;

    use HasUuids;
    use RegistaActividade;

    /**
     * @return array{estado: class-string<EstadoDocumento>, valor: string, data_documento: string}
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'estado' => EstadoDocumento::class,
            'valor' => 'decimal:2',
            'data_documento' => 'date',
        ];
    }

    /**
     * Campos sensíveis excluídos do audit trail (RGPD / PII indirecta).
     *
     * @return list<string>
     */
    protected function atributosExcluidosDaActividade(): array
    {
        return ['hash_sha256', 'disco_storage', 'nome_ficheiro_storage'];
    }

    public function estado(): EstadoDocumentoInterface
    {
        return match ($this->estado) {
            EstadoDocumento::Pendente => DocumentoPendente::deDocumento($this),
            EstadoDocumento::AnaliseMalware => DocumentoAnaliseMalware::deDocumento($this),
            EstadoDocumento::AnaliseTexto => DocumentoAnaliseTexto::deDocumento($this),
            EstadoDocumento::AnaliseOcr => DocumentoAnaliseOcr::deDocumento($this),
            EstadoDocumento::AnaliseIaLocal => DocumentoAnaliseIaLocal::deDocumento($this),
            EstadoDocumento::AnaliseCloud => DocumentoAnaliseCloud::deDocumento($this),
            EstadoDocumento::Processado => DocumentoProcessado::deDocumento($this),
            EstadoDocumento::Erro => DocumentoErro::deDocumento($this),
            EstadoDocumento::Perigoso => DocumentoPerigoso::deDocumento($this),
        };
    }

    /** @return BelongsTo<Entidade, $this> */
    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Entidade::class, 'id_fornecedor')->withTrashed();
    }

    /** @return BelongsTo<Entidade, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Entidade::class, 'id_cliente')->withTrashed();
    }

    /** @return BelongsTo<CategoriaDocumento, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaDocumento::class, 'id_categoria')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_responsavel');
    }

    /** @return HasMany<EtapaDocumento, $this> */
    public function historico(): HasMany
    {
        return $this->hasMany(EtapaDocumento::class, 'id_documento')->orderBy('created_at');
    }

    /** @return HasOne<ExtracaoDocumento, $this> */
    public function extracao(): HasOne
    {
        return $this->hasOne(ExtracaoDocumento::class, 'id_documento');
    }

    /** @param Builder<Documento> $query */
    public function scopeWhereEstado(Builder $query, EstadoDocumento $estado): void
    {
        $query->where('estado', $estado);
    }

    /** @param Builder<Documento> $query */
    public function scopeWhereProcessado(Builder $query): void
    {
        $query->where('estado', EstadoDocumento::Processado);
    }

    /** @param Builder<Documento> $query */
    public function scopeWherePendente(Builder $query): void
    {
        $query->where('estado', EstadoDocumento::Pendente);
    }

    /** @param Builder<Documento> $query */
    public function scopeWherePerigoso(Builder $query): void
    {
        $query->where('estado', EstadoDocumento::Perigoso);
    }

    /** @param Builder<Documento> $query */
    public function scopeWhereErro(Builder $query): void
    {
        $query->where('estado', EstadoDocumento::Erro);
    }

    /**
     * Documentos num dos `$estados` transitórios cujo `updated_at` é mais antigo
     * que `$limiarMinutos` — candidatos a reconciliação ficheiro↔BD (`ReconciliarFicheirosJob`).
     *
     * @param  Builder<Documento>  $query
     * @param  list<EstadoDocumento>  $estados
     */
    public function scopeWherePresos(Builder $query, array $estados, int $limiarMinutos): void
    {
        $query->whereIn('estado', $estados)->where('updated_at', '<', now()->subMinutes($limiarMinutos));
    }
}
