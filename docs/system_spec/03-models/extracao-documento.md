# System Spec — Model: ExtracaoDocumento

> `app/Models/ExtracaoDocumento.php` · Tabela: `extracoes_documento`

---

## Tabela `extracoes_documento`

| Coluna | Tipo BD | Nullable | Default | Notas |
|---|---|---|---|---|
| `id` | `uuid` PK | Não | — | UUIDv7 via `HasUuids` |
| `id_documento` | `uuid` FK | Não | — | **UNIQUE** (1-1 com `documentos`); `cascadeOnDelete()` + `cascadeOnUpdate()` |
| `extracao_reclamada_em` | `timestamp` | Sim | `null` | Lease de reivindicação; TTL = `config('extracao.ttl_lease')` (300s) — gravado por `ReivindicarDocumentoEmEtapaAction` (`updateOrCreate`), não é explicitamente limpo (o `RegistarEtapaExtracaoAction` da etapa seguinte já reclama de novo) |
| `extracao_tentativas` | `unsignedTinyInteger` | Não | `0` | Tecto = `config('extracao.max_tentativas')` (3), enforcement em `RegistarFalhaTecnicaExtracaoAction`; reposto a 0 no avanço correcto de etapa por `RegraReporTentativasExtracao` (`02-shared/regras-transicao-documento.md`) |
| `texto_extraido` | `longText` | Sim | `null` | PII — nunca em Resource |
| `dados_json` | `json` | Sim | `null` | PII — nunca em Resource; cast `array` (array shape opcional por entidade — ver Notas arquitecturais); **não populado por nenhum orquestrador** — ver Notas arquitecturais |
| `created_at` / `updated_at` | `timestamp` | — | — | `timestamps()` — tabela **mutável** (ao contrário de `etapas_documento`, que é append-only) |

Índice simples `(extracao_reclamada_em)` — consumido por `ReivindicarDocumentoEmEtapaAction`
(`01-features/documento-pipeline.md`) para seleccionar o próximo documento elegível por etapa (lease
nulo ou expirado). (A coluna de estado da extracção deixou de existir: o progresso lê-se de
`Documento.estado` na máquina de estados unificada — issue #110.)

**Scratch space 1-1 com `Documento`** — sem coluna de estado; só produtos intermédios da extracção
(`texto_extraido`/`dados_json`), lease e contador de tentativas. `id_documento` é `unique()`; nunca
existem duas linhas para o mesmo documento (upsert por esta chave, ver `RegistarEtapaExtracaoAction`
abaixo). Eliminada ao atingir estado terminal — ver `RegraEliminarExtracaoTerminal`
(`02-shared/regras-transicao-documento.md`).

---

## Model `ExtracaoDocumento`

**Ficheiro:** `app/Models/ExtracaoDocumento.php`

```php
#[Table('extracoes_documento')]
#[Fillable([
    'id_documento', 'extracao_reclamada_em',
    'extracao_tentativas', 'texto_extraido', 'dados_json',
])]
class ExtracaoDocumento extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
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
 * @property-read ?Carbon $extracao_reclamada_em
 * @property-read int $extracao_tentativas
 * @property-read ?string $texto_extraido
 * @property-read ?array{
 *     data_documento?: string,
 *     fornecedor?: array{nif?: string, nome?: string},
 *     cliente?: array{nif?: string, nome?: string},
 *     valor?: float,
 * } $dados_json
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

Base (`definition()`) = scratch space vazio: `extracao_tentativas: 0`, lease e dados a `null`.

| State | Efeito | Notas |
|---|---|---|
| base | scratch vazio | sem tentativas, lease nem dados |
| `reclamada()` | `extracao_reclamada_em: now()` | testa o campo de lease (elegibilidade em `ReivindicarDocumentoEmEtapaAction`) |
| `comDadosExtraidos()` | `texto_extraido` + `dados_json` preenchidos | simula extracção concluída |
| `comTentativas(int)` | `extracao_tentativas: N` | contador de tentativas |

---

## Recorder — `RegistarEtapaExtracaoAction`

**Ficheiro:** `app/Features/Documento/Processamento/RegistarEtapaExtracao/RegistarEtapaExtracaoAction.php`

Único ponto de **escrita** (upsert) em `extracoes_documento`. As **eliminações** ocorrem em
`RegraEliminarExtracaoTerminal` (ao entrar num estado terminal) e no `delete()` defensivo de
`ReprocessarDocumentoAction`. Ver `01-features/documento-pipeline.md` para o detalhe completo (DTO,
contrato "substituição total", ausência de `Gate::authorize`).

---

## Notas arquitecturais

- **Sem Repository** — CRUD simples (1 upsert por PK única, sem lógica de query complexa); mesmo
  critério de `ListarDocumentosAction` (`04-infra/repositories.md`).
- **Model não é `final`** — coerente com `Documento`/`EtapaDocumento`; o ArchTest "actions are final"
  não cobre Models.
- **`dados_json` tipado com array shape de chaves opcionais** (`data_documento?`, `fornecedor?`,
  `cliente?`, `valor?`) em vez de `mixed` — inferida de `ResultadoExtracaoIA`
  (`04-infra/extracao-ia.md`). Mesmo com os 4 orquestradores de etapa implementados, nenhum escreve
  `dados_json` — `RegistarFalhaTecnicaExtracaoAction` só **preserva** o valor já existente (contrato
  de substituição total do recorder) e `ConcluirExtracaoDocumentoAction` passa o `ResultadoExtracaoIA`
  directamente ao DTO de transição, sem passar por `dados_json` — populá-lo ficou fora de âmbito.
  Este shape continua a **guiar** uma implementação futura, não é um contrato já imposto por código.
- **Índice `(extracao_reclamada_em)` consumido** — deixou de ser preparação especulativa:
  `ReivindicarDocumentoEmEtapaAction` filtra por este campo em toda reivindicação de etapa. Mesmo
  padrão do índice `(estado, updated_at)` de `Documento`, agora ambos com consumidor real.
