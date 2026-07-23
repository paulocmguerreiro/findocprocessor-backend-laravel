# System Spec — Feature: Documento (pipeline em background)

> `app/Features/Documento/` — Actions e componentes **sem endpoint HTTP**, invocados apenas
> programaticamente (Commands `extracao:*` agendados no `Schedule` + `ReconciliarFicheirosJob`).
> Superfície HTTP (criação/leitura/transições retidas, DTOs, Events, Controller/Resources,
> autorização) em `01-features/documento.md`.

---

## Visão geral

18 Actions sem endpoint HTTP: 8 de transição simples (5 `MarcarAnalise*` + `MarcarErro` +
`MarcarPerigoso` + `TransicionarProcessadoDocumentoAction`), 3 de reivindicação/triagem
(`ReivindicarDocumentoPendenteAction`, `ProcessarAnaliseMalwareDocumentoAction`,
`ReivindicarDocumentoEmEtapaAction` — `app/Features/Documento/Atribuicao/`),
`RegistarEtapaExtracaoAction` (recorder de extracção) e **6 Actions do pipeline automático de
extracção** (`app/Features/Documento/Processamento/`, ver secção dedicada abaixo): os 4
orquestradores de etapa (`ProcessarAnaliseTexto`/`ProcessarAnaliseOcr`/`ProcessarAnaliseIaLocal`/
`ProcessarAnaliseCloud`) + as duas Actions partilhadas que invocam (`ConcluirExtracaoDocumentoAction`,
`RegistarFalhaTecnicaExtracaoAction`). Todas correm em background, sem utilizador autenticado — ver
"Transições de sistema (sem Gate)" no fim.

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
`TransicionarProcessadoDocumentoDto` — `idFornecedor`/`idCliente`/`valor`/`dataDocumento` são
**nullable** (documentos "parciais": extractos/avisos onde um dos lados não tem contraparte real,
`espera_<lado> = false`); `idCategoria` continua sempre obrigatório. Só faz `findOrFail` do fornecedor
quando `idFornecedor` não é nulo. Emite `DocumentoProcessadoEvent`. Alcançável do fim do pipeline de
IA (`AnaliseIaLocal` directo ou via `AnaliseCloud`), sempre através de `ConcluirExtracaoDocumentoAction`
(secção "Orquestradores de etapa" abaixo).

`MarcarErroDocumentoAction` — DTO `MarcarErroDocumentoDto` (campo `mensagemErro`). Emite
`DocumentoMarcadoErroEvent`. Alcançável de **qualquer** passo de análise que falhe (`AnaliseMalware`,
`AnaliseTexto`, `AnaliseOcr`, `AnaliseIaLocal`, `AnaliseCloud`) — genérica, sem alteração de código
entre os casos.

`MarcarPerigosoDocumentoAction` — DTO `MarcarPerigosoDocumentoDto` (campo `motivo`). Alcançável de
`AnaliseMalware` (scan) e dos passos de IA `AnaliseIaLocal`/`AnaliseCloud` (guardrail de conteúdo).
Emite `DocumentoMarcadoPerigosoEvent`.

---

## Actions de triagem e reivindicação de pipeline

| Action | Ability | De | Para | Move ficheiro |
|---|---|---|---|---|
| `ReivindicarDocumentoPendenteAction` | — (sem Gate, sistema) | `Pendente` | `AnaliseTexto`/`Perigoso`/`Erro` (via `ProcessarAnaliseMalwareDocumentoAction`) | Não/origem → `perigoso`/`erro` |
| `ProcessarAnaliseMalwareDocumentoAction` | — (sem Gate, sistema) | `Pendente` | `AnaliseMalware` → `AnaliseTexto`/`Perigoso`/`Erro` | conforme a Action delegada |

`ReivindicarDocumentoPendenteAction` (`app/Features/Documento/Atribuicao/`) — reutilizada
por `ExecutarScanExtracaoCommand` (`extracao:run-scan`, `04-infra/queue-jobs.md`): abre
`DB::transaction()` (ponto de entrada, sem Action chamante), bloqueia (`lockForUpdate()`) o próximo
`Documento` `Pendente` (scope `wherePendente()`) e delega em `ProcessarAnaliseMalwareDocumentoAction` (transação
aninhada via `SAVEPOINT`).
Evita que dois workers concorrentes reivindiquem o mesmo documento — ver `04-infra/transactions.md`
para o padrão completo e `07-testing.md` para o teste de concorrência real (duas conexões MySQL).

`ProcessarAnaliseMalwareDocumentoAction` (`app/Features/Documento/Processamento/`) — admite primeiro o `Documento` a
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

**`RegistarEtapaExtracaoAction`** (`app/Features/Documento/Processamento/`) — recorder do
pipeline: dado um `Documento` e um `RegistarEtapaExtracaoDto`, faz upsert (por `id_documento`, chave
única) da linha em `extracoes_documento` (scratch space: `texto_extraido`/`dados_json`/lease/tentativas)
e grava uma `EtapaDocumento` com `estado` igual ao estado actual do documento — que **é** já o passo
de análise em curso (`AnaliseTexto`, `AnaliseIaLocal`, …) — e `resultado` (Sucesso/Falha/EmCurso), tudo
na mesma `DB::transaction()`. Não altera `Documento.estado` nem usa `RegraTransicaoEstado` (não é uma
transição de negócio; o passo já é o estado). Contrato "substituição total": cada chamada substitui
inteiramente `texto_extraido`/`dados_json` — o chamador (os orquestradores de etapa, secção abaixo)
envia sempre o valor completo pretendido, nunca deltas. Sem `Gate::authorize` (acção de sistema) — `EtapaDocumento` gravada
com `id_utilizador = null`. Ver `03-models/extracao-documento.md`.

---

## Reivindicação por lease das etapas de análise

| Action | Ability | Escreve | Devolve |
|---|---|---|---|
| `ReivindicarDocumentoEmEtapaAction` | — (sem Gate, sistema) | `extracoes_documento.extracao_reclamada_em` (upsert) | `Documento\|null` |

**`ReivindicarDocumentoEmEtapaAction`** (`app/Features/Documento/Atribuicao/`)
— ao contrário de `ReivindicarDocumentoPendenteAction` (a mudança de estado `Pendente → AnaliseMalware`
já garante exclusão mútua), as 4 etapas de análise (`AnaliseTexto`/`AnaliseOcr`/`AnaliseIaLocal`/
`AnaliseCloud`) **não mudam de estado** ao serem reclamadas — a exclusão entre workers assenta num
lease com TTL (`extracao_reclamada_em`). Dentro de uma `DB::transaction()`: selecciona sob
`lockForUpdate()` o documento mais antigo no `EstadoDocumento` pedido cujo lease é nulo ou expirado
(`extracao_reclamada_em < now - config('extracao.ttl_lease')`, default 300s — `EXTRACAO_TTL_LEASE`),
grava `extracao_reclamada_em = now()` (`updateOrCreate` — cria a linha `extracoes_documento` se ainda
não existir) e devolve o `Documento`; `null` se não houver candidato. Sem `Gate` (sistema). Ver
`04-infra/transactions.md` para o padrão de concorrência e `07-testing.md` para o teste com 2 conexões
MySQL (`ReivindicarDocumentoEmEtapaConcorrenciaTest`).

---

## Orquestradores de etapa (pipeline automático de extracção)

Uma Action por estado de análise textual/IA — reclama por lease (secção acima), chama o motor puro de
`app/Infrastructure/` correspondente, interpreta o resultado e transiciona. Ficheiro fino: sem lógica
de motor, sem `Gate::authorize()` (acção de sistema). Invocadas sincronamente pelos Commands
`extracao:*` (`04-infra/queue-jobs.md`), não por Job em fila.

| Action | Motor invocado | Encaminhamento |
|---|---|---|
| `ProcessarAnaliseTextoDocumentoAction` | `ExtractorTextoNativo` (só PDF) | não-PDF → `AnaliseOcr` sem chamar o parser; PDF acima do threshold → `AnaliseIaLocal`; abaixo → `AnaliseOcr`; falha técnica → `RegistarFalhaTecnicaExtracaoAction` |
| `ProcessarAnaliseOcrDocumentoAction` | `ExtractorOcr` (Tesseract) | sucesso → `AnaliseIaLocal`; falha técnica → `RegistarFalhaTecnicaExtracaoAction` |
| `ProcessarAnaliseIaLocalDocumentoAction` | `ClienteIAInterface` (`CamadaIA::Local`) | camada inactiva (`extracao.local.activa`) → `AnaliseCloud` **sem** contar tentativa; veredicto completo → `ConcluirExtracaoDocumentoAction`; perigoso → `Perigoso`; desconhecido/incompleto → `AnaliseCloud`; falha técnica → `RegistarFalhaTecnicaExtracaoAction` |
| `ProcessarAnaliseCloudDocumentoAction` | `ClienteIAInterface` (`CamadaIA::Cloud`) | camada inactiva (`extracao.cloud.activa`) → `Erro` directo (`sem LLM cloud disponível`), sem contar tentativa; veredicto completo → `ConcluirExtracaoDocumentoAction`; perigoso → `Perigoso`; desconhecido/incompleto → `Erro` (última camada, sem escalar); falha técnica → `RegistarFalhaTecnicaExtracaoAction` |

`ProcessarAnaliseTextoDocumentoAction` detecta imagem vs. PDF pela extensão de
`nome_ficheiro_storage` (`pathinfo(...)['extension']`, preservada pelo `hashName()` do upload) — não
pela extensão original do utilizador. Os dois orquestradores de IA leem o `texto_extraido` mais
recente directamente de `ExtracaoDocumento` (não recebem o texto por parâmetro).

### Actions partilhadas (evitam duplicar lógica entre os 4 orquestradores)

**`ConcluirExtracaoDocumentoAction`** (`app/Features/Documento/Processamento/ConcluirExtracao/`) —
conclusão de um veredicto **completo**, idêntica em `AnaliseIaLocal`/`AnaliseCloud`: resolve
`RegraReconciliarEntidadesDocumento` (secção seguinte) e transiciona para `Processado`
(`TransicionarProcessadoDocumentoAction`). Empresa mãe não configurada (`ModelNotFoundException` do
`firstOrFail`) → `Erro` directo, sem contar tentativa (config operacional em falta, RN-06). Como
`TransicionarProcessadoDocumentoAction` é o único passo do pipeline com `Gate::authorize('update')` e
o pipeline corre sem sessão HTTP, a transição executa **autenticada como o responsável do documento**
(`Documento.id_responsavel`, o autor do upload — ver `project_jobs_correm_como_primeiro_utilizador`),
com restauro explícito (`Auth::login`/`Auth::logout`) do utilizador anterior no `finally`.

**`RegistarFalhaTecnicaExtracaoAction`** (`app/Features/Documento/Processamento/`)
— tecto de tentativas técnicas (RF-12), partilhado pelos 4 orquestradores: regista a falha via
`RegistarEtapaExtracaoAction` (preservando `texto_extraido`/`dados_json` já existentes — contrato de
substituição total do recorder) e incrementa `extracao_tentativas`; ao atingir
`config('extracao.max_tentativas')` (3) transiciona para `Erro`, caso contrário devolve o `Documento`
inalterado (retentado no próximo ciclo). Só falhas **técnicas** (excepção do motor/cliente) passam por
aqui — saltos semânticos (threshold, veredicto, camada inactiva) nunca incrementam (RN-04).

### Reconciliação de entidades por NIF

**`RegraReconciliarEntidadesDocumento`** (`app/Features/Documento/Processamento/ConcluirExtracao/`,
invocada por `ConcluirExtracaoDocumentoAction`) — dado um `ResultadoExtracaoIA` completo (emissor=
fornecedor, destinatário=cliente) e o `TipoDocumento` classificado, situa a empresa mãe por
**correspondência de NIF** (não por posição sugerida ao modelo — a extração é role-neutral, ver
`04-infra/prompt-builder.md`):

| Condição do lado | Resolução | `id_<lado>` |
|---|---|---|
| NIF do lado == NIF da empresa mãe | `Entidade::whereEmpresaAplicacao()->firstOrFail()` (singleton) | preenchido |
| lado com NIF (e não é a empresa mãe) | `firstOrCreate` por `nif` normalizado (`nome` + flag `e_fornecedor`/`e_cliente`) | preenchido |
| lado sem NIF (e não é a empresa mãe) | não cria `Entidade` | `null` |

O NIF é a chave (o nome pode abreviar/variar; o NIF ou está completo ou não serve). Se o NIF da mãe
não coincidir com **nenhum** dos lados, o documento não a envolve → `ModelNotFoundException` (o
orquestrador encaminha para `Erro`). Sem empresa mãe configurada, `firstOrFail()` lança a mesma
excepção. Ambas são apanhadas por `ConcluirExtracaoDocumentoAction`.

**Correcção de tipo/categoria pela direcção:** a direcção da empresa mãe (por NIF — fornecedor vs
cliente) é a fonte de verdade da categoria. Se o `TipoDocumento` classificado pela IA tiver a
`posicao_empresa_mae` contrária (ex.: uma venda de serviços emitida pela mãe classificada como um
tipo de "cliente"), a regra re-selecciona o único tipo com a posição correcta — a IA classifica a
**natureza**, o NIF decide o **sentido** (compra vs venda).

Devolve `ResultadoReconciliacaoEntidades` (VO: `idFornecedor`, `idCliente`, `idCategoria` — do tipo
resolvido pela direcção —, `nomeFornecedorParaNome` — o nome extraído, ou o nome da empresa mãe se o
extraído vier vazio, usado como fallback de `RegraNomearProcessado`). A duplicação de `Entidade` por
NIF imperfeito entre chamadas da IA é risco aceite (mitigado por uma futura funcionalidade de agrupar
entidades duplicadas) — aqui a idempotência é estrita por `nif` normalizado.

---

## Executor partilhado interno

### `ExecutorTransicaoDocumento`

**Ficheiro:** `app/Features/Documento/Operacoes/Transicao/ExecutorTransicaoDocumento.php`

Orquestrador partilhado pelas 10 Actions de transição. Encapsula a mecânica comum:

```
regraTransicao->handle($de, $para)   ← valida De→Para
regraMover->handle(...)              ← move ficheiro (fora da transação)
DB::transaction()
  documento->update([estado, disco, nome, ...campos domínio])
  regraEliminarExtracao->handle($documento, $novoEstado)  ← se terminal, apaga ExtracaoDocumento (RGPD)
  regraReporTentativas->handle($documento, $novoEstado)   ← avanço não-terminal: extracao_tentativas = 0
  historico()->create([estado, motivo, id_utilizador])
  cache->invalidarCache(Documentos)
  Event::dispatch($evento($documento))  ← se evento fornecido
catch (\Throwable)
  regraMover->handle(...)            ← compensação: repor na origem
  throw $erro
```

`RegraReporTentativasExtracao` (RN-05/RF-13) repõe `extracao_tentativas = 0` sempre que o documento
avança para um estado **não-terminal** — a nova etapa arranca com o orçamento de tentativas cheio;
**nunca** reposto numa transição para `Erro`. No-op se não existir `ExtracaoDocumento`. Catálogo
completo em `02-shared/regras-transicao-documento.md`.

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
O mapa central é validado por `RegraTransicaoEstado` (ver `02-shared/regras-transicao-documento.md`).

| De               | Para             | Action                                  | Via                  |
| ---------------- | ---------------- | --------------------------------------- | -------------------- |
| `Pendente`       | `AnaliseMalware` | `MarcarAnaliseMalwareDocumentoAction` (via `ProcessarAnaliseMalwareDocumentoAction`) | pipeline |
| `AnaliseMalware` | `AnaliseTexto`   | `MarcarAnaliseTextoDocumentoAction` (via `ProcessarAnaliseMalwareDocumentoAction`)   | pipeline (scan limpo) |
| `AnaliseMalware` | `Perigoso`       | `MarcarPerigosoDocumentoAction` (via `ProcessarAnaliseMalwareDocumentoAction`)       | pipeline (infectado) |
| `AnaliseMalware` | `Erro`           | `MarcarErroDocumentoAction` (via `ProcessarAnaliseMalwareDocumentoAction`)           | pipeline (falha do scan) |
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

> A dimensão de extracção (`ExtracaoDocumento` como scratch space) e o contrato de atomicidade
> ficheiro↔BD (reconciliação) estão documentados em `01-features/documento-reconciliacao.md`.

---

## Transições de sistema (sem Gate)

As 7 transições intermédias de pipeline **não têm `Gate::authorize`** — correm sempre em background
(Commands `extracao:*` agendados no `Schedule`, `04-infra/queue-jobs.md`), sem utilizador autenticado:
as 5 `MarcarAnalise*` (`MarcarAnaliseMalwareDocumentoAction`, `MarcarAnaliseTextoDocumentoAction`,
`MarcarAnaliseOcrDocumentoAction`, `MarcarAnaliseIaLocalDocumentoAction`,
`MarcarAnaliseCloudDocumentoAction`), `MarcarErroDocumentoAction` e `MarcarPerigosoDocumentoAction`. A
`EtapaDocumento` que gravam fica como **passo de sistema** (`id_utilizador = null`).
`ReivindicarDocumentoPendenteAction`, `ReivindicarDocumentoEmEtapaAction`, `ProcessarAnaliseMalwareDocumentoAction`,
`RegistarEtapaExtracaoAction`, os 4 orquestradores de etapa (`ProcessarAnalise*`) e
`RegistarFalhaTecnicaExtracaoAction` seguem o mesmo padrão — sem Gate.

O `TransicionarProcessadoDocumentoAction` é a excepção: apesar de não ter endpoint, **mantém
`Gate::authorize('update')`** porque escreve os dados de negócio extraídos (fornecedor, valor,
categoria, nome canónico) — é um write significativo, não uma mera flag de estado. Ver padrão em
`02-shared/padroes-acoes.md`. Como o pipeline automático corre sem sessão HTTP,
`ConcluirExtracaoDocumentoAction` autentica temporariamente como o responsável do documento
(`id_responsavel`) só para satisfazer este Gate, restaurando o utilizador anterior (ou logout) logo a
seguir — ver secção "Orquestradores de etapa" acima.
