# System Spec — Feature: Documento (pipeline em background)

> `app/Features/Documento/` — Actions e componentes **sem endpoint HTTP**, invocados apenas
> programaticamente (Jobs de extracção, futuro orquestrador de IA/OCR). Superfície HTTP
> (criação/leitura/transições retidas, DTOs, Events, Controller/Resources, autorização) em
> `01-features/documento.md`.

---

## Visão geral

11 Actions sem endpoint HTTP: 8 de transição simples (5 `MarcarAnalise*` + `MarcarErro` +
`MarcarPerigoso` + `TransicionarProcessadoDocumentoAction`), `ReivindicarDocumentoPendenteAction` +
`TriarDocumentoPendenteAction` (reivindicação/triagem) e `RegistarEtapaExtracaoAction` (recorder de
extracção). Todas correm em background, sem utilizador autenticado — ver "Transições de sistema (sem
Gate)" no fim.

> **Máquina de estados unificada:** a extracção corre localmente, por isso cada passo de análise
> (`AnaliseMalware`, `AnaliseTexto`, `AnaliseOcr`, `AnaliseIaLocal`, `AnaliseCloud`) é um **estado
> próprio** de `EstadoDocumento` — não existe já uma segunda dimensão `EtapaExtracao`. O histórico de
> IA é gravado como uma `EtapaDocumento` cujo `estado` é o passo em curso (ver "Recorder").

---

## Actions de transição de pipeline (sem endpoint HTTP)

| Action | Ability | De | Para | Move ficheiro |
|---|---|---|---|---|
| `MarcarAnaliseMalwareDocumentoAction` | — (sistema) | `Pendente` | `AnaliseMalware` | Não (fica em `entrada`) |
| `MarcarAnaliseTextoDocumentoAction` | — (sistema) | `AnaliseMalware` | `AnaliseTexto` | Não (fica em `entrada`) |
| `MarcarAnaliseOcrDocumentoAction` | — (sistema) | `AnaliseTexto` | `AnaliseOcr` | Não (fica em `entrada`) |
| `MarcarAnaliseIaLocalDocumentoAction` | — (sistema) | `AnaliseTexto` ou `AnaliseOcr` | `AnaliseIaLocal` | `entrada → enviado` |
| `MarcarAnaliseCloudDocumentoAction` | — (sistema) | `AnaliseIaLocal` | `AnaliseCloud` | Não (fica em `enviado`) |
| `TransicionarProcessadoDocumentoAction` | `update` | `AnaliseIaLocal` ou `AnaliseCloud` | `Processado` | `enviado → processado` + rename |
| `MarcarErroDocumentoAction` | — (sistema) | qualquer `Analise*` | `Erro` | origem → `erro` |
| `MarcarPerigosoDocumentoAction` | — (sistema) | `AnaliseMalware`, `AnaliseIaLocal` ou `AnaliseCloud` | `Perigoso` | origem → `perigoso` |

Todas delegam em `ExecutorTransicaoDocumento::executar()`.

As 5 `MarcarAnalise*` são flags de transição de sistema (sem `Gate`, sem DTO próprio) — cada uma
apenas move o `Documento` para o passo de análise seguinte; um `motivo` opcional distingue casos
(ex.: `MarcarAnaliseTexto($documento, 'scan de malware desligado')`).

`TransicionarProcessadoDocumentoAction` — preenche campos de domínio (fornecedor/cliente/categoria/
valor/data); usa `RegraNomearProcessado` para gerar o nome canónico. DTO:
`TransicionarProcessadoDocumentoDto`. Emite `DocumentoProcessado`. Alcançável do fim do pipeline de
IA (`AnaliseIaLocal` directo ou via `AnaliseCloud`).

`MarcarErroDocumentoAction` — DTO `MarcarErroDocumentoDto` (campo `mensagemErro`). Emite
`DocumentoMarcadoErro`. Alcançável de **qualquer** passo de análise que falhe (`AnaliseMalware`,
`AnaliseTexto`, `AnaliseOcr`, `AnaliseIaLocal`, `AnaliseCloud`) — genérica, sem alteração de código
entre os casos.

`MarcarPerigosoDocumentoAction` — DTO `MarcarPerigosoDocumentoDto` (campo `motivo`). Alcançável de
`AnaliseMalware` (scan) e dos passos de IA `AnaliseIaLocal`/`AnaliseCloud` (guardrail de conteúdo).
Emite `DocumentoMarcadoPerigoso`.

---

## Actions de triagem e reivindicação de pipeline

| Action | Ability | De | Para | Move ficheiro |
|---|---|---|---|---|
| `ReivindicarDocumentoPendenteAction` | — (sem Gate, sistema) | `Pendente` | `AnaliseTexto`/`Perigoso`/`Erro` (via `TriarDocumentoPendenteAction`) | Não/origem → `perigoso`/`erro` |
| `TriarDocumentoPendenteAction` | — (sem Gate, sistema) | `Pendente` | `AnaliseMalware` → `AnaliseTexto`/`Perigoso`/`Erro` | conforme a Action delegada |

`ReivindicarDocumentoPendenteAction` (`app/Features/Documento/Reivindicar/`) — componente reutilizável
de reivindicação para o futuro orquestrador de IA: abre `DB::transaction()` (ponto de entrada, sem
Action chamante), bloqueia (`lockForUpdate()`) o próximo `Documento` `Pendente` (scope
`wherePendente()`) e delega em `TriarDocumentoPendenteAction` (transação aninhada via `SAVEPOINT`).
Evita que dois workers concorrentes reivindiquem o mesmo documento — ver `04-infra/transactions.md`
para o padrão completo e `07-testing.md` para o teste de concorrência real (duas conexões MySQL).

`TriarDocumentoPendenteAction` (`app/Features/Documento/Triar/`) — admite primeiro o `Documento` a
`AnaliseMalware` (`Pendente → AnaliseMalware`, via `MarcarAnaliseMalwareDocumentoAction`), depois corre
o `AnalisadorMalware` sobre o ficheiro (disco `entrada`), **dentro da mesma transacção/lock** que o
reivindica (não abre transacção própria), e ramifica a partir de `AnaliseMalware`: infectado →
`MarcarPerigosoDocumentoAction` (motivo = assinatura); limpo → `MarcarAnaliseTextoDocumentoAction`;
não configurado (camada `clamd` inactiva) → `MarcarAnaliseTextoDocumentoAction` com motivo "scan de
malware desligado"; falha do scan (`FalhaAnaliseMalwareException`) → `MarcarErroDocumentoAction` com o
motivo = razão da falha. Ver `04-infra/malware.md` para o contrato `AnalisadorMalware`.

---

## Recorder de extracção

| Action | Ability | Escreve | Move ficheiro |
|---|---|---|---|
| `RegistarEtapaExtracaoAction` | — (sem Gate, sistema) | `extracoes_documento` (upsert) + `EtapaDocumento` (`resultado`) | Não |

**`RegistarEtapaExtracaoAction`** (`app/Features/Documento/RegistarEtapaExtracao/`) — recorder do
pipeline: dado um `Documento` e um `RegistarEtapaExtracaoDto`, faz upsert (por `id_documento`, chave
única) da linha em `extracoes_documento` (scratch space: `texto_extraido`/`dados_json`/lease/tentativas)
e grava uma `EtapaDocumento` com `estado` igual ao estado actual do documento — que **é** já o passo
de análise em curso (`AnaliseTexto`, `AnaliseIaLocal`, …) — e `resultado` (Sucesso/Falha/EmCurso), tudo
na mesma `DB::transaction()`. Não altera `Documento.estado` nem usa `RegraTransicaoEstado` (não é uma
transição de negócio; o passo já é o estado). Contrato "substituição total": cada chamada substitui
inteiramente `texto_extraido`/`dados_json` — o chamador (futuro orquestrador) envia sempre o valor
completo pretendido, nunca deltas. Sem `Gate::authorize` (acção de sistema) — `EtapaDocumento` gravada
com `id_utilizador = null`. Ver `03-models/extracao-documento.md`.

---

## Executor partilhado interno

### `ExecutorTransicaoDocumento`

**Ficheiro:** `app/Features/Documento/Transicao/ExecutorTransicaoDocumento.php`

Orquestrador partilhado pelas 10 Actions de transição. Encapsula a mecânica comum:

```
regraTransicao->handle($de, $para)   ← valida De→Para
regraMover->handle(...)              ← move ficheiro (fora da transação)
DB::transaction()
  documento->update([estado, disco, nome, ...campos domínio])
  regraEliminarExtracao->handle($documento, $novoEstado)  ← se terminal, apaga ExtracaoDocumento (RGPD)
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

| De               | Para             | Action                                  | Via                  |
| ---------------- | ---------------- | --------------------------------------- | -------------------- |
| `Pendente`       | `AnaliseMalware` | `MarcarAnaliseMalwareDocumentoAction` (via `TriarDocumentoPendenteAction`) | pipeline |
| `AnaliseMalware` | `AnaliseTexto`   | `MarcarAnaliseTextoDocumentoAction` (via `TriarDocumentoPendenteAction`)   | pipeline (scan limpo) |
| `AnaliseMalware` | `Perigoso`       | `MarcarPerigosoDocumentoAction` (via `TriarDocumentoPendenteAction`)       | pipeline (infectado) |
| `AnaliseMalware` | `Erro`           | `MarcarErroDocumentoAction` (via `TriarDocumentoPendenteAction`)           | pipeline (falha do scan) |
| `AnaliseTexto`   | `AnaliseOcr`     | `MarcarAnaliseOcrDocumentoAction`       | pipeline (texto insuficiente) |
| `AnaliseTexto`   | `AnaliseIaLocal` | `MarcarAnaliseIaLocalDocumentoAction`   | pipeline             |
| `AnaliseTexto`   | `Erro`           | `MarcarErroDocumentoAction`             | pipeline             |
| `AnaliseOcr`     | `AnaliseIaLocal` | `MarcarAnaliseIaLocalDocumentoAction`   | pipeline             |
| `AnaliseOcr`     | `Erro`           | `MarcarErroDocumentoAction`             | pipeline             |
| `AnaliseIaLocal` | `AnaliseCloud`   | `MarcarAnaliseCloudDocumentoAction`     | pipeline (IA local insuficiente) |
| `AnaliseIaLocal` | `Processado`     | `TransicionarProcessadoDocumentoAction` | pipeline             |
| `AnaliseIaLocal` | `Perigoso`       | `MarcarPerigosoDocumentoAction`         | pipeline (guardrail) |
| `AnaliseIaLocal` | `Erro`           | `MarcarErroDocumentoAction`             | pipeline             |
| `AnaliseCloud`   | `Processado`     | `TransicionarProcessadoDocumentoAction` | pipeline             |
| `AnaliseCloud`   | `Perigoso`       | `MarcarPerigosoDocumentoAction`         | pipeline (guardrail) |
| `AnaliseCloud`   | `Erro`           | `MarcarErroDocumentoAction`             | pipeline             |
| `Erro`           | `Pendente`       | `ReprocessarDocumentoAction`            | HTTP (reabre pipeline) |
| `Processado`     | `Processado`     | `CorrigirDocumentoAction`               | HTTP (self-loop)     |

Qualquer par não listado lança `TransicaoInvalidaException` (→ 422).

Os state objects (`02-shared/estados.md`) são read-only — sem método `correct()`. A transição, o
movimento de ficheiro entre discos e o registo em `EtapaDocumento` são feitos pelas Actions acima.

---

## Dimensão de extracção — `ExtracaoDocumento` como scratch space

Com a máquina de estados **unificada**, o passo de análise **é** o `Documento.estado` — deixou de
existir a coluna `EtapaExtracao`/dimensão paralela. `ExtracaoDocumento` reduz-se a **scratch space**
1-1 com o `Documento`: `texto_extraido`/`dados_json` (produtos intermédios da extracção, PII),
`extracao_reclamada_em` (lease) e `extracao_tentativas` (contador). Nenhuma coluna de estado — o
progresso lê-se de `Documento.estado`.

- **`ExtracaoDocumento` é opcional** — `Documento::extracao()` devolve `null` para qualquer documento
  que nunca tenha entrado no pipeline de extracção (registo manual via `RegistarDocumentoManualAction`,
  ou documento marcado `Perigoso`/`Erro` no scan de malware antes de qualquer escrita de extracção).
  Ver `03-models/extracao-documento.md`.
- **Eliminada ao atingir estado terminal** — `RegraEliminarExtracaoTerminal` (invocada pelo
  `ExecutorTransicaoDocumento` dentro da transacção) apaga a linha ao entrar em `Processado`, `Erro`
  ou `Perigoso` (minimização de dados / RGPD). Ver `02-shared/regras-negocio.md`. Um documento
  reaberto (`Erro → Pendente`) recomeça o pipeline sem herdar scratch space antigo;
  `ReprocessarDocumentoAction` mantém um `delete()` defensivo idempotente como rede de segurança.
- **`etapas_documento` regista a história completa** — uma linha de transição de negócio (gravada por
  `ExecutorTransicaoDocumento`) tem `resultado` a `null`; uma linha de tentativa de IA (gravada por
  `RegistarEtapaExtracaoAction`) tem `estado` igual ao passo de análise em curso e `resultado`
  preenchido — permite reconstruir a história numa única query ordenada por `created_at`.
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
  presos num estado transitório (`AnaliseMalware`/`AnaliseTexto`/`AnaliseOcr`/`AnaliseIaLocal`/
  `AnaliseCloud` — os 5 passos de análise) há mais tempo que
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

As 7 transições intermédias de pipeline **não têm `Gate::authorize`** — correm sempre em background
(Jobs de extracção), sem utilizador autenticado: as 5 `MarcarAnalise*`
(`MarcarAnaliseMalwareDocumentoAction`, `MarcarAnaliseTextoDocumentoAction`,
`MarcarAnaliseOcrDocumentoAction`, `MarcarAnaliseIaLocalDocumentoAction`,
`MarcarAnaliseCloudDocumentoAction`), `MarcarErroDocumentoAction` e `MarcarPerigosoDocumentoAction`. A
`EtapaDocumento` que gravam fica como **passo de sistema** (`id_utilizador = null`).
`ReivindicarDocumentoPendenteAction`, `TriarDocumentoPendenteAction` e `RegistarEtapaExtracaoAction`
seguem o mesmo padrão — sem Gate.

O `TransicionarProcessadoDocumentoAction` é a excepção: apesar de não ter endpoint, **mantém
`Gate::authorize('update')`** porque escreve os dados de negócio extraídos (fornecedor, valor,
categoria, nome canónico) — é um write significativo, não uma mera flag de estado. Ver padrão em
`02-shared/padroes-acoes.md`.
