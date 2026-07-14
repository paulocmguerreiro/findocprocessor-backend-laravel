# System Spec — Model: ExtracaoDocumento

> `app/Models/ExtracaoDocumento.php` · Tabela: `extracoes_documento` · Issue #94

---

## Tabela `extracoes_documento`

| Coluna | Tipo BD | Nullable | Default | Notas |
|---|---|---|---|---|
| `id` | `uuid` PK | Não | — | UUIDv7 via `HasUuids` |
| `id_documento` | `uuid` FK | Não | — | **UNIQUE** (1-1 com `documentos`); `cascadeOnDelete()` + `cascadeOnUpdate()` |
| `etapa_extracao` | `string(50)` | Não | `'PENDENTE'` | Cast → `EtapaExtracao`; etapa actual da extracção |
| `extracao_reclamada_em` | `timestamp` | Sim | `null` | Lease de reivindicação; TTL = `config('extracao.ttl_lease')` (300s, #95) — **libertado pelo orquestrador (#97/#98)**, esta issue só grava/limpa o valor |
| `extracao_tentativas` | `unsignedTinyInteger` | Não | `0` | Tecto = `config('extracao.max_tentativas')` (3, #95) — **enforcement fora desta issue** |
| `texto_extraido` | `longText` | Sim | `null` | PII — nunca em Resource |
| `dados_json` | `json` | Sim | `null` | PII — nunca em Resource; cast `array` (`array<string, mixed>`, chaves não previsíveis) |
| `created_at` / `updated_at` | `timestamp` | — | — | `timestamps()` — tabela **mutável** (ao contrário de `etapas_documento`, que é append-only) |

Índice composto `(etapa_extracao, extracao_reclamada_em)` — preparação para o `SELECT` do futuro
Schedule (#97/#98); sem consumidor nesta issue.

**1-1 com `Documento`** — `id_documento` é `unique()`; nunca existem duas linhas para o mesmo documento
(upsert por esta chave, ver `RegistarEtapaExtracaoAction` abaixo).

---

## Model `ExtracaoDocumento`

**Ficheiro:** `app/Models/ExtracaoDocumento.php`

```php
#[Table('extracoes_documento')]
#[Fillable([
    'id_documento', 'etapa_extracao', 'extracao_reclamada_em',
    'extracao_tentativas', 'texto_extraido', 'dados_json',
])]
class ExtracaoDocumento extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'etapa_extracao' => EtapaExtracao::class,
            'extracao_reclamada_em' => 'datetime',
            'extracao_tentativas' => 'integer',
            'dados_json' => 'array',
        ];
    }
}
```

**Sem `RegistaActividade`** — a coluna `dados_json`/`texto_extraido` é PII; um audit trail técnico
duplicaria dados sensíveis fora do controlo dos Resources (RGPD).

### PHPDoc `@property-read`

```php
/**
 * @property-read string $id
 * @property-read string $id_documento
 * @property-read EtapaExtracao $etapa_extracao
 * @property-read ?Carbon $extracao_reclamada_em
 * @property-read int $extracao_tentativas
 * @property-read ?string $texto_extraido
 * @property-read ?array<string, mixed> $dados_json
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read Documento $documento
 */
```

### Relações

```php
public function documento(): BelongsTo // → Documento (id_documento)
```

Relação inversa: `Documento::extracao(): HasOne` — ver `03-models/documento.md`.

---

## Factory `ExtracaoDocumentoFactory`

**Ficheiro:** `database/factories/ExtracaoDocumentoFactory.php`

Base (`definition()`) = `etapa_extracao Pendente`, `extracao_tentativas: 0`, resto `null`.

| State | `etapa_extracao` | Notas |
|---|---|---|
| base | `Pendente` | sem tentativas, lease nem dados |
| `necessitaOcr()` | `NecessitaOcr` | — |
| `textoPronto()` | `TextoPronto` | `texto_extraido` preenchido |
| `necessitaCloud()` | `NecessitaCloud` | — |
| `concluido()` | `Concluido` | `texto_extraido` + `dados_json` preenchidos |
| `falhado()` | `Falhado` | `extracao_tentativas: 3` |
| `reclamada()` | (mantém a etapa actual) | `extracao_reclamada_em: now()` — testa o campo de lease mesmo sem orquestrador |

---

## Recorder — `RegistarEtapaExtracaoAction`

**Ficheiro:** `app/Features/Documento/RegistarEtapaExtracao/RegistarEtapaExtracaoAction.php`

Único ponto de escrita em `extracoes_documento` fora do reset de `ReprocessarDocumentoAction`. Ver
`01-features/documento.md` para o detalhe completo (DTO, contrato "substituição total", ausência de
`Gate::authorize`).

---

## Notas arquitecturais

- **Sem Repository** — CRUD simples (1 upsert por PK única, sem lógica de query complexa); mesmo
  critério de `ListarDocumentosAction` (`04-infra/repositories.md`).
- **Model não é `final`** — coerente com `Documento`/`EtapaDocumento`; o ArchTest "actions are final"
  não cobre Models.
- **`dados_json` tipado `array<string, mixed>`** — as chaves são dados extraídos livres (NIF, nomes,
  valores), não previsíveis estaticamente; `array<string, T>` em vez de `array{...}` (Regra A,
  `02-shared/padroes-tipagem.md`, adaptada — aqui as chaves não são conhecidas à partida).
- **Índice composto sem consumidor** — aceite, mesmo padrão do índice `(status, updated_at)` de
  `Documento` antes do #90 (preparação para uso futuro documentado, não especulação sem plano).
