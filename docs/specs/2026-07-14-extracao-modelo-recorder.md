# Spec: Extração — registo de passos de IA + histórico unificado (model + recorder)

**Issue:** #94
**Brief:** docs/briefs/2026-07-14-extracao-modelo-recorder.md
**Data:** 2026-07-14

## Requisitos funcionais

- RF-01: Existe uma tabela `extracoes_documento`, 1-1 com `documentos` (`id_documento` **UNIQUE**),
  que regista a etapa actual de extração de um `Documento` — independente do seu `status` de
  negócio.
- RF-02: Existe um Model `App\Models\ExtracaoDocumento` (`HasUuids`, sem `RegistaActividade`) com
  relação inversa `documento(): BelongsTo`.
- RF-03: `Documento` ganha a relação `extracao(): HasOne` — `null` quando o documento nunca entrou
  no pipeline de extração (ex.: registo manual via `RegistarDocumentoManualAction`, que vai directo
  a `Processado`/`Perigoso`/`Erro` sem passar pela dimensão de extração).
- RF-04: Existe uma Action `RegistarEtapaExtracaoAction` (`app/Features/Documento/RegistarEtapaExtracao/`)
  que, dado um `Documento` e um `RegistarEtapaExtracaoDto`, faz upsert da linha em
  `extracoes_documento` correspondente e grava uma `EtapaDocumento` com `passo`/`resultado`
  preenchidos (linha de IA) na mesma `DB::transaction()`.
- RF-05: `etapas_documento` ganha as colunas nullable `passo` (cast `EtapaExtracao`) e `resultado`
  (cast `ResultadoEtapa`). Uma linha de negócio (gravada por `ExecutorTransicaoDocumento`) continua
  a ter ambas a `null`; uma linha de IA (gravada por `RegistarEtapaExtracaoAction`) tem `estado`
  igual ao `status` actual do `Documento` (não muda) e `passo`/`resultado` preenchidos.
- RF-06: `EtapaDocumentoResource` expõe `passo` e `resultado` (`->value` ou `null`).
  `DocumentoResource` expõe `etapa_extracao` (string ou `null`) via `whenLoaded('extracao')` — nunca
  expõe `texto_extraido` nem `dados_json`.
- RF-07: `ReprocessarDocumentoAction` (`Erro → AguardaEnvio`), ao reabrir um documento, reseta a
  linha `extracoes_documento` desse documento **se existir** (`etapa_extracao = Pendente`,
  `extracao_reclamada_em = null`, `extracao_tentativas = 0`, `texto_extraido = null`,
  `dados_json = null`) — sem criar linha nova se nenhuma existir (documento nunca entrou no
  pipeline de extração, ex.: erro de scan de malware em `Pendente`).

## Requisitos não funcionais

- RNF-01: `texto_extraido`/`dados_json` nunca aparecem em nenhum Resource JSON exposto por HTTP
  (verificável por teste de `EtapaDocumentoResource`/`DocumentoResource`/`ExtracaoDocumento` — sem
  Resource próprio para `ExtracaoDocumento`, dado que não tem endpoint).
- RNF-02: `RegistarEtapaExtracaoAction` não tem `Gate::authorize()` — acção de sistema, mesmo
  padrão das transições `Marcar*` (`02-shared/padroes-acoes.md`); `EtapaDocumento` gravada por esta
  Action tem sempre `id_utilizador = null`.
- RNF-03: 100% code coverage / type coverage / Larastan nível 9 zero erros (`composer test`).

## Modelo de dados

### Tabela nova `extracoes_documento`

| Coluna | Tipo BD | Nullable | Default | Notas |
|---|---|---|---|---|
| `id` | `uuid` PK | Não | — | `HasUuids` |
| `id_documento` | `uuid` FK | Não | — | **UNIQUE**; → `documentos.id`; `cascadeOnDelete()` |
| `etapa_extracao` | `string(50)` | Não | `'PENDENTE'` | cast `EtapaExtracao` |
| `extracao_reclamada_em` | `timestamp` | Sim | `null` | lease/lock; TTL = `config('extracao.ttl_lease')` (300s, #95) — **liberto pelo orquestrador (#97/#98)**, esta issue só grava/limpa o valor |
| `extracao_tentativas` | `unsignedTinyInteger` | Não | `0` | tecto = `config('extracao.max_tentativas')` (3, #95) — **enforcement fora desta issue** |
| `texto_extraido` | `longText` | Sim | `null` | PII — nunca em Resource |
| `dados_json` | `json` | Sim | `null` | PII — nunca em Resource |
| `created_at`/`updated_at` | `timestamp` | — | — | tabela mutável (não append-only, ao contrário de `etapas_documento`) |

Índice composto `(etapa_extracao, extracao_reclamada_em)` — preparação para o SELECT do futuro
Schedule (#97/#98); sem consumidor nesta issue.

### `etapas_documento` — 2 colunas novas

| Coluna | Tipo BD | Nullable | Notas |
|---|---|---|---|
| `passo` | `string(50)` | Sim | cast `EtapaExtracao`; `null` = linha de negócio |
| `resultado` | `string(20)` | Sim | cast `ResultadoEtapa`; `null` = linha de negócio |

### Enums novos (`app/Shared/Enums/`)

```php
enum EtapaExtracao: string
{
    case Pendente       = 'PENDENTE';
    case NecessitaOcr    = 'NECESSITA_OCR';
    case TextoPronto     = 'TEXTO_PRONTO';
    case NecessitaCloud  = 'NECESSITA_CLOUD';
    case Concluido       = 'CONCLUIDO';
    case Falhado         = 'FALHADO';
}

enum ResultadoEtapa: string
{
    case Sucesso = 'SUCESSO';
    case Falha   = 'FALHA';
    case EmCurso = 'EM_CURSO';
}
```

## Regras de negócio

- RN-01: `extracoes_documento.id_documento` é **UNIQUE** — upsert por este campo (nunca duas linhas
  para o mesmo `Documento`).
- RN-02: `RegistarEtapaExtracaoAction` grava sempre `EtapaDocumento.estado = $documento->status`
  (não muda o estado de negócio) — apenas `passo`/`resultado`/`motivo` variam.
- RN-03: `ReprocessarDocumentoAction` abre a sua **própria** `DB::transaction()` (novo, este método
  não a tinha) que envolve tanto a chamada a `ExecutorTransicaoDocumento::executar()` (transacção
  aninhada / `SAVEPOINT`, mesmo padrão de `ReivindicarDocumentoPendenteAction` +
  `MarcarAguardaEnvioDocumentoAction`, `04-infra/transactions.md`) como o `update()` condicional da
  linha `extracoes_documento` — atomicidade entre a transição de negócio e o reset da dimensão de
  extração. `ExecutorTransicaoDocumento` **não é alterado**.
- RN-04: O reset em RN-03 usa `update()` (nunca `create()`/`upsert()`) — se não existir linha
  `extracoes_documento` para o documento, não é criada uma (documento nunca entrou na dimensão de
  extração).
- RN-05: `Documento::extracao()` é `HasOne` sem `withDefault()` — `null` é um valor legítimo (não
  "ainda não carregada").
- RN-06: `RegistarEtapaExtracaoAction` acede `ExtracaoDocumento` directamente via Eloquent, sem
  Repository — desvio aceite por CRUD simples (1 upsert por PK única, sem lógica de query
  complexa), mesmo critério de `04-infra/repositories.md` já aplicado a `ListarDocumentosAction`.

## Dependências

- Issues bloqueantes: nenhuma.
- Depende de `config/extracao.php` (`ttl_lease`, `max_tentativas`) — já implementado, issue #95.
- Pré-requisito do orquestrador de pipeline (#97/#98), que é quem efectivamente invoca
  `RegistarEtapaExtracaoAction` em produção e implementa a reivindicação (`lockForUpdate` +
  libertação por TTL) sobre `extracao_reclamada_em`.

## Questões resolvidas

| Questão (do Brief) | Decisão |
|---|---|
| TTL do lease de reclamação | Reutilizar `config('extracao.ttl_lease')` (#95, default 300s); esta issue só grava o timestamp, sem lógica de expiração/libertação (fica no orquestrador, #97/#98) |
| Tecto de `extracao_tentativas` e o que fazer ao esgotar | Reutilizar `config('extracao.max_tentativas')` (#95, default 3); a coluna regista o contador — o *enforcement* (transição automática a `Erro` vs. revisão manual) fica fora desta issue |
| Purgar `texto_extraido` após `PROCESSADO` | Diferido para a issue do orquestrador (#97/#98) — implicaria alterar `TransicionarProcessadoDocumentoAction`, fora do âmbito "model + recorder" desta issue |
| Nome final da tabela/modelo | `extracoes_documento` / `App\Models\ExtracaoDocumento` (recomendação da issue) |

## Critérios de aceitação

> Issue #94 não lista CAs explícitos (formato "Âmbito" + "Decisões em aberto") — os CAs abaixo
> traduzem o Âmbito da issue em critérios verificáveis, todos marcados *(spec)*.

- [ ] CA-01: Migration cria `extracoes_documento` com FK `id_documento` UNIQUE + `cascadeOnDelete`
  e índice composto `(etapa_extracao, extracao_reclamada_em)`. *(spec)*
- [ ] CA-02: `ExtracaoDocumento` — Model + Factory (states pelos 6 casos de `EtapaExtracao`) + testes
  de casts/relação. *(spec)*
- [ ] CA-03: Migration adiciona `passo`/`resultado` (nullable) a `etapas_documento`, sem quebrar
  linhas existentes (ambas `null` por omissão). *(spec)*
- [ ] CA-04: `RegistarEtapaExtracaoAction` + `RegistarEtapaExtracaoDto` — upsert em
  `extracoes_documento` + `historico()->create()` na mesma transacção; sem `Gate::authorize`; testes
  cobrem happy path, rollback (falha a meio não deixa `EtapaDocumento` órfã sem `ExtracaoDocumento`
  actualizada, e vice-versa) e reinvocação (upsert idempotente por `id_documento`). *(spec)*
- [ ] CA-05: `Documento::extracao()` (`HasOne`) + `@property-read` — devolve `null` para documento
  manual (sem linha). *(spec)*
- [ ] CA-06: `EtapaDocumentoFactory::passoIa()` — novo state com `passo`/`resultado` preenchidos.
  *(spec)*
- [ ] CA-07: `EtapaDocumentoResource`/`DocumentoResource` actualizados; teste confirma que
  `texto_extraido`/`dados_json` nunca aparecem no JSON serializado, mesmo com a relação carregada.
  *(spec)*
- [ ] CA-08: `ReprocessarDocumentoAction` reseta `extracoes_documento` quando existe linha, não cria
  quando não existe; teste de rollback confirma atomicidade (falha no reset reverte também a
  transição de estado). *(spec)*
- [ ] CA-09: `composer test` verde (Larastan L9, type-coverage 100%, coverage 100%, arch). *(spec)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/03-models/extracao-documento.md` — **novo ficheiro** (Model, migration, Factory
  de `ExtracaoDocumento`)
- `docs/system_spec/03-models/etapa-documento.md` — colunas `passo`/`resultado`, Factory
  `passoIa()`
- `docs/system_spec/03-models/documento.md` — relação `extracao()`
- `docs/system_spec/02-shared/enums.md` — `EtapaExtracao`, `ResultadoEtapa`
- `docs/system_spec/02-shared/estados.md` — secção "modelo de 2 dimensões" (estado de negócio vs.
  etapa de extração), com a tabela de mapeamento da issue
- `docs/system_spec/01-features/documento.md` — nova Action `RegistarEtapaExtracaoAction` (sem
  HTTP) + ripple em `ReprocessarDocumentoAction`
- `docs/system_spec/04-infra/queue-jobs.md` — nota: `RegistarEtapaExtracaoAction` é o ponto de
  invocação programática que o futuro orquestrador (#97/#98) vai chamar
- `docs/system_spec/04-infra/external-apis.md` — actualizar tabela "Integrações planeadas" (sem
  mudança de estado, só referência ao novo modelo)
- `docs/system_spec/00-index.md` — linha nova em "Modelos Eloquent" (`ExtracaoDocumento`) + contagem
  de Actions em `Documento` actualizada (17)

## Verificação RGPD/NIS2

- Dados pessoais: `texto_extraido`/`dados_json` podem conter NIF, nomes, valores extraídos do
  documento. Mitigação: tabela sem `RegistaActividade` (sem log técnico duplicado de PII); nunca
  expostos em Resource; `EtapaDocumento.motivo` (já existente) permanece o único campo textual do
  histórico exposto via `EtapaDocumentoResource`.
- Superfície de ataque: nenhuma rota HTTP nova — `extracoes_documento` só é escrita/lida
  programaticamente (recorder + futuro orquestrador). Sem novo vector de input externo nesta issue.
