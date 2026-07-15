# System Spec — Feature: Documento (pipeline em background)

> `app/Features/Documento/` — Actions e componentes **sem endpoint HTTP**, invocados apenas
> programaticamente (Jobs de extracção, futuro orquestrador de IA/OCR). Superfície HTTP
> (criação/leitura/transições retidas, DTOs, Events, Controller/Resources, autorização) em
> `01-features/documento.md`.

---

## Visão geral

9 Actions sem endpoint HTTP: 6 de transição simples (`Marcar*` +
`TransicionarProcessadoDocumentoAction`), `ReivindicarDocumentoPendenteAction` +
`TriarDocumentoPendenteAction` (reivindicação/triagem) e `RegistarEtapaExtracaoAction` (recorder de
extracção). Todas correm em background, sem utilizador autenticado — ver "Transições de sistema (sem
Gate)" no fim.

---

## Actions de transição de pipeline (sem endpoint HTTP)

| Action | Ability | De | Para | Move ficheiro |
|---|---|---|---|---|
| `MarcarAguardaEnvioDocumentoAction` | `update` | `Pendente` | `AguardaEnvio` | Não (fica em `entrada`) |
| `MarcarEnviadoDocumentoAction` | `update` | `AguardaEnvio` | `Enviado` | `entrada → enviado` |
| `MarcarAguardaRespostaDocumentoAction` | `update` | `Enviado` | `AguardaResposta` | Não (fica em `enviado`) |
| `TransicionarProcessadoDocumentoAction` | `update` | `AguardaResposta` | `Processado` | `enviado → processado` + rename |
| `MarcarErroDocumentoAction` | `update` | `AguardaResposta` ou `Pendente` | `Erro` | origem → `erro` |
| `MarcarPerigosoDocumentoAction` | `update` | `Pendente` ou `AguardaResposta` | `Perigoso` | origem → `perigoso` |

Todas delegam em `ExecutorTransicaoDocumento::executar()`.

`TransicionarProcessadoDocumentoAction` — preenche campos de domínio (fornecedor/cliente/categoria/
valor/data); usa `RegraNomearProcessado` para gerar o nome canónico. DTO:
`TransicionarProcessadoDocumentoDto`. Emite `DocumentoProcessado`.

`MarcarErroDocumentoAction` — DTO `MarcarErroDocumentoDto` (campo `mensagemErro`). Emite
`DocumentoMarcadoErro`. Alcançável de `AguardaResposta` (falha de envio/resposta) e de `Pendente`
(falha do scan de malware) — genérica, sem alteração de código entre os dois casos.

`MarcarPerigosoDocumentoAction` — DTO `MarcarPerigosoDocumentoDto` (campo `motivo`). Alcançável de
dois estados (`Pendente` pré-scan e `AguardaResposta` guardrail). Emite `DocumentoMarcadoPerigoso`.

---

## Actions de triagem e reivindicação de pipeline

| Action | Ability | De | Para | Move ficheiro |
|---|---|---|---|---|
| `ReivindicarDocumentoPendenteAction` | — (sem Gate, sistema) | `Pendente` | `AguardaEnvio`/`Perigoso`/`Erro` (via `TriarDocumentoPendenteAction`) | Não/origem → `perigoso`/`erro` |
| `TriarDocumentoPendenteAction` | — (sem Gate, sistema) | `Pendente` | `AguardaEnvio`/`Perigoso`/`Erro` | conforme a Action delegada |

`ReivindicarDocumentoPendenteAction` (`app/Features/Documento/Reivindicar/`) — componente reutilizável
de reivindicação para o futuro orquestrador de IA: abre `DB::transaction()` (ponto de entrada, sem
Action chamante), bloqueia (`lockForUpdate()`) o próximo `Documento` `Pendente` (scope
`wherePendente()`) e delega em `TriarDocumentoPendenteAction` (transação aninhada via `SAVEPOINT`).
Evita que dois workers concorrentes reivindiquem o mesmo documento — ver `04-infra/transactions.md`
para o padrão completo e `07-testing.md` para o teste de concorrência real (duas conexões MySQL).

`TriarDocumentoPendenteAction` (`app/Features/Documento/Triar/`) — corre o `AnalisadorMalware`
sobre o ficheiro do `Documento` `Pendente`, **dentro da mesma transacção/lock** que o reivindica (não
abre transacção própria), e ramifica: infectado → `MarcarPerigosoDocumentoAction` (motivo =
assinatura); limpo → `MarcarAguardaEnvioDocumentoAction`; não configurado (camada `clamd`
inactiva) → `MarcarAguardaEnvioDocumentoAction` com motivo "scan de malware desligado"; falha do
scan (`FalhaAnaliseMalwareException`) → `MarcarErroDocumentoAction` com o motivo = razão da falha.
Ver `04-infra/malware.md` para o contrato `AnalisadorMalware`.

---

## Recorder de extracção

| Action | Ability | Escreve | Move ficheiro |
|---|---|---|---|
| `RegistarEtapaExtracaoAction` | — (sem Gate, sistema) | `extracoes_documento` (upsert) + `EtapaDocumento` (`passo`/`resultado`) | Não |

**`RegistarEtapaExtracaoAction`** (`app/Features/Documento/RegistarEtapaExtracao/`) — recorder do
pipeline: dado um `Documento` e um `RegistarEtapaExtracaoDto`, faz upsert (por `id_documento`, chave
única) da linha em `extracoes_documento` e grava uma `EtapaDocumento` com `estado` igual ao estado
actual do documento (não muda) e `passo`/`resultado` preenchidos — tudo na mesma `DB::transaction()`.
Não altera `Documento.estado` nem usa `RegraTransicaoEstado` (não é uma transição de negócio).
Contrato "substituição total": cada chamada substitui inteiramente `texto_extraido`/`dados_json` — o
chamador (futuro orquestrador) envia sempre o valor completo pretendido, nunca deltas. Sem
`Gate::authorize` (acção de sistema) — `EtapaDocumento` gravada com `id_utilizador = null`.
Ver `03-models/extracao-documento.md` e "Modelo de 2 dimensões" abaixo.

---

## Executor partilhado interno

### `ExecutorTransicaoDocumento`

**Ficheiro:** `app/Features/Documento/Transicao/ExecutorTransicaoDocumento.php`

Orquestrador partilhado pelas 8 Actions de transição simples. Encapsula a mecânica comum:

```
regraTransicao->handle($de, $para)   ← valida De→Para
regraMover->handle(...)              ← move ficheiro (fora da transação)
DB::transaction()
  documento->update([estado, disco, nome, ...campos domínio])
  historico()->create([estado, motivo, id_utilizador])
  cache->invalidarCache(Documentos)
  Event::dispatch($evento($documento))  ← se evento fornecido
catch (\Throwable)
  regraMover->handle(...)            ← compensação: repor na origem
  throw $erro
```

**Assinatura:**
```php
executar(
    Documento $documento,
    EstadoDocumento $novoEstado,
    string $motivo,
    array $camposDominio = [],
    ?string $nomeDestino = null,
    ?Closure $evento = null,       // factory: fn(Documento): Event
): Documento
```

Não é uma Action — não tem `Gate::authorize()` própria. A autorização é sempre feita pela Action
chamante antes de invocar `executar()`.

---

## Regras de transição — mapa De → Para

A mudança de estado é sempre feita por Actions de transição, **nunca** com `if ($doc->estado == ...)`.
O mapa central é validado por `RegraTransicaoEstado` (ver `02-shared/regras-negocio.md`).

| De                | Para              | Action                                  | Via                  |
| ----------------- | ----------------- | --------------------------------------- | -------------------- |
| `Pendente`        | `AguardaEnvio`    | `MarcarAguardaEnvioDocumentoAction` (via `TriarDocumentoPendenteAction`) | pipeline |
| `Pendente`        | `Perigoso`        | `MarcarPerigosoDocumentoAction` (via `TriarDocumentoPendenteAction`)    | pipeline (pré-scan)  |
| `Pendente`        | `Erro`            | `MarcarErroDocumentoAction` (via `TriarDocumentoPendenteAction`)        | pipeline (falha do scan de malware) |
| `AguardaEnvio`    | `Enviado`         | `MarcarEnviadoDocumentoAction`          | pipeline             |
| `Enviado`         | `AguardaResposta` | `MarcarAguardaRespostaDocumentoAction`  | pipeline             |
| `AguardaResposta` | `Processado`      | `TransicionarProcessadoDocumentoAction` | pipeline             |
| `AguardaResposta` | `Erro`            | `MarcarErroDocumentoAction`             | pipeline             |
| `AguardaResposta` | `Perigoso`        | `MarcarPerigosoDocumentoAction`         | pipeline (guardrail) |
| `Erro`            | `AguardaEnvio`    | `ReprocessarDocumentoAction`            | HTTP                 |
| `Processado`      | `Processado`      | `CorrigirDocumentoAction`               | HTTP (self-loop)     |

Qualquer par não listado lança `TransicaoInvalidaException` (→ 422).

Os state objects (`02-shared/estados.md`) são read-only — sem método `correct()`. A transição, o
movimento de ficheiro entre discos e o registo em `EtapaDocumento` são feitos pelas Actions acima.

---

## Modelo de 2 dimensões — estado de negócio × etapa de extracção

`Documento.estado` (`EstadoDocumento`) e `ExtracaoDocumento.etapa_extracao` (`EtapaExtracao`) são
**duas dimensões independentes**: o estado de negócio segue o ciclo de vida do documento
(`02-shared/estados.md`); a etapa de extracção segue o progresso do pipeline de IA/OCR sobre o
conteúdo do ficheiro. Um documento pode, por exemplo, estar em `AguardaResposta` (negócio) enquanto a
sua extracção está em `NecessitaOcr` (IA) — os dois avançam a ritmos diferentes e são geridos por
Actions distintas (`Marcar*`/`ExecutorTransicaoDocumento` vs. `RegistarEtapaExtracaoAction`).

| Estado de negócio (`estado`) | Etapa de extracção possível | Lock (`extracao_reclamada_em`) |
|---|---|---|
| `Pendente` | Sem linha `ExtracaoDocumento` (ainda não reivindicado) ou `Pendente` | `null` |
| `AguardaEnvio`, `Enviado`, `AguardaResposta` | `Pendente` → `NecessitaOcr`/`TextoPronto`/`NecessitaCloud` → `Concluido`/`Falhado` | preenchido enquanto reivindicado pelo orquestrador; `null` fora da janela de lease |
| `Processado` | Tipicamente `Concluido` (não enforçado nesta fase) | `null` |
| `Erro` | Etapa congelada no valor anterior à transição — **resetada para `Pendente`** ao reprocessar (`ReprocessarDocumentoAction`) | `null` após reset |
| `Perigoso` | Documento nunca chega a ter linha de extracção nesta transição (ficheiro nunca é enviado ao pipeline de IA) | — |

- **`ExtracaoDocumento` é opcional** — `Documento::extracao()` devolve `null` para qualquer documento
  que nunca tenha entrado na dimensão de extracção (registo manual via
  `RegistarDocumentoManualAction`, ou erro de scan de malware em `Pendente` antes de qualquer
  reivindicação). Ver `03-models/extracao-documento.md`.
- **`etapas_documento` regista ambas as dimensões na mesma tabela** — uma linha de negócio (gravada
  por `ExecutorTransicaoDocumento`) tem `passo`/`resultado` a `null`; uma linha de IA (gravada por
  `RegistarEtapaExtracaoAction`) tem `estado` igual ao estado **actual** do documento (não muda) e
  `passo`/`resultado` preenchidos — permite reconstruir a história completa (negócio + IA) numa única
  query ordenada por `created_at`.
- **Enforcement adiado**: reivindicação real com `lockForUpdate()`/libertação por TTL sobre
  `extracao_reclamada_em`, e a transição automática ao esgotar `extracao_tentativas`, ficam para o
  orquestrador de pipeline. Por agora só existem o modelo de dados e o recorder.

---

## Contrato de atomicidade ficheiro↔BD

`ExecutorTransicaoDocumento` move o ficheiro **antes** de abrir a `DB::transaction()` (ver
`04-infra/transactions.md`) — o filesystem não participa no rollback da BD. Se a compensação
best-effort (repor o ficheiro na origem) também falhar, existe uma **janela de inconsistência**:
a BD reflecte o estado anterior à transição, mas o ficheiro físico pode estar no disco de destino.

Como o conjunto de discos é fixo (5: `entrada`, `enviado`, `processado`, `erro`, `perigoso`, mapa em
`RegraMoverFicheiro::discoParaEstado()`), esta janela é **detectável e reversível**, não uma
inconsistência permanente:

- **Detecção:** `ReconciliarFicheirosJob` (agendado a cada 5 min, `onOneServer`) varre `Documento`s
  presos num estado transitório (`AguardaEnvio`/`Enviado`/`AguardaResposta`) há mais tempo que
  `config('pipeline.reconciliacao_limiar_minutos')` (default 15 min — não é uma janela de
  recência, é um limiar de "parado há mais tempo que uma transição normal demora").
- **Resolução:** `RegraReconciliarLocalizacaoFicheiro` verifica se o ficheiro existe no
  `disco_storage` actual; se não, procura-o nos 4 discos restantes comparando `hash_sha256` (o
  nome mantém-se igual entre discos, excepto no caso `Processado`/`RegraNomearProcessado`, fora do
  âmbito desta reconciliação). Se localizado noutro disco, `ReconciliarFicheirosJob` **repõe
  automaticamente** `disco_storage`/`nome_ficheiro_storage` na BD.
- **Caso irrecuperável:** se o ficheiro não existir em nenhum dos 5 discos, o Job regista
  `Log::error` estruturado (id do documento, disco/nome esperados — sem dados sensíveis) e não
  altera a BD; um ficheiro genuinamente perdido exige intervenção manual, fora do âmbito da
  reconciliação automática.
- **Custo:** proporcional ao nº de documentos presos (scan limitado pelo índice composto
  `(estado, updated_at)`), nunca à tabela `documentos` completa.

---

## Transições de sistema (sem Gate)

As 5 transições intermédias de pipeline **não têm `Gate::authorize`** — correm sempre em background
(Jobs de extracção), sem utilizador autenticado: `MarcarAguardaEnvioDocumentoAction`,
`MarcarEnviadoDocumentoAction`, `MarcarAguardaRespostaDocumentoAction`, `MarcarErroDocumentoAction`,
`MarcarPerigosoDocumentoAction`. A `EtapaDocumento` que gravam fica como **passo de sistema**
(`id_utilizador = null`). `ReivindicarDocumentoPendenteAction`, `TriarDocumentoPendenteAction` e
`RegistarEtapaExtracaoAction` seguem o mesmo padrão — sem Gate.

O `TransicionarProcessadoDocumentoAction` é a excepção: apesar de não ter endpoint, **mantém
`Gate::authorize('update')`** porque escreve os dados de negócio extraídos (fornecedor, valor,
categoria, nome canónico) — é um write significativo, não uma mera flag de estado. Ver padrão em
`02-shared/padroes-acoes.md`.
