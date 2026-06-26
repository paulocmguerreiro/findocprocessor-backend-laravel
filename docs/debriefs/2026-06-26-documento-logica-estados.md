# Debrief: Documento — lógica (máquina de estados)

**Issue:** #57
**Branch:** feat/documento-logica-estados
**Data:** 2026-06-26
**Spec:** docs/specs/2026-06-26-documento-logica-estados.md

---

## Resumo

Issue de maior envergadura até agora: 12 tarefas, 11 Actions de transição + listagem/ver/download,
3 classes `Regra*`, 5 DTOs novos, 4 Events, 1 orquestrador interno e camada HTTP completa.
527 testes, 100% coverage, 100% type coverage, Larastan 9 zero erros. Todos os CAs da spec satisfeitos.

---

## Decisões tomadas

### D1 — `ExecutorTransicaoDocumento` emergiu como orquestrador partilhado

**O que aconteceu:** A spec previa que cada Action de transição orquestraria ela própria
`RegraTransicaoEstado` + `RegraMoverFicheiro` + persistência + `EtapaDocumento` + cache + evento.
Ao implementar a segunda Action ficou evidente que a mecânica é 100% idêntica em todas as 8
transições simples — apenas o estado destino, motivo, campos de domínio extra e evento diferem.

**Decisão:** Extraiu-se `ExecutorTransicaoDocumento` (`final readonly class`), interno à feature
`Documento/Transicao/`. Cada Action delega nele via `executar()`. As Actions ficam com ≤ 10 linhas
cada e só carregam o que é específico (estado destino, evento factory). Não viola Vertical Slice
porque não sai da feature `Documento`.

**Alternativa rejeitada:** Duplicar a mecânica nas 8 Actions (mais verboso, mais propenso a
divergência se a mecânica mudar).

---

### D2 — Atomicidade ficheiro↔BD: mover ANTES da transação, compensar em falha

**O que aconteceu:** A spec (Q1) decidiu "mover dentro da transação". Na prática, ao rever a
implementação de `RegraMoverFicheiro`, ficou claro que o cenário de falha é mais perigoso do lado
oposto: se o ficheiro for movido dentro da transação e o commit falhar, o filesystem já tem o
ficheiro no disco destino mas a BD reverteu para o estado anterior — estado BD ≠ disco.

**Decisão adoptada no `ExecutorTransicaoDocumento`:** mover o ficheiro **antes** de abrir a
transação. Se a transação (ou o commit) falhar, o `catch (\Throwable)` tenta mover o ficheiro de
volta (compensação best-effort). Resultado: em caso de falha rara, o ficheiro pode ficar no disco
errado em cenários de dupla falha (mover + compensar), mas em falha simples a compensação repõe a
consistência. É o mesmo grau de best-effort que a spec acordou, mas com a ordem (mover→transação) em
vez de (transação com mover dentro).

**Limitação conhecida registada:** em cenário de dupla falha (mover OK, commit falha, compensar
falha), a BD fica no estado anterior e o ficheiro fica no disco destino — inconsistência persistente.
Reconciliação manual necessária. Aceitável para esta issue; mitigação real exigiria um saga/outbox.

---

### D3 — `RegistarDocumentoManualDto` e `CorrigirDocumentoDto` são DTOs novos (não os da #45)

**O que aconteceu:** A spec previa reusar `CriarDocumentoManualDto` (#45) em
`RegistarDocumentoManualAction` e `ActualizarDocumentoDto` (#45) em `CorrigirDocumentoAction`,
adicionando apenas `fromRequest()`. Ao implementar ficou claro que os DTOs da #45 tinham campos de
storage (`hash_sha256`, `disco_storage`, `nome_ficheiro_storage`) que não devem vir do cliente — são
derivados pela Action. Enviar esses campos no DTO seria violar o princípio "campos de ficheiro nunca
vêm do cliente".

**Decisão:** DTOs totalmente novos e independentes dos da #45:
- `RegistarDocumentoManualDto` — tem `ficheiro: UploadedFile` mas não tem campos de storage; a Action
  calcula `hash_sha256`, nome canónico e escreve no disco `processado`.
- `CorrigirDocumentoDto` — só campos de domínio (`idFornecedor`, `idCliente`, `idCategoria`, `valor`,
  `dataDocumento`); sem nenhum campo de storage.

Os DTOs `CriarDocumentoManualDto` e `ActualizarDocumentoDto` da #45 ficaram obsoletos — removidos
nesta issue (zero callers confirmado por grep; `ActualizarDocumentoDto` em `Actualizar/` era código
morto puro).

---

### D4 — Ordenação `RegraTransicaoEstado`: `Reprocessar` e `MarcarAguardaEnvio` partilham destino `AguardaEnvio`

Confirmado que o mapa central tem dois arcos chegando a `AguardaEnvio`:
- `Pendente → AguardaEnvio` (pipeline normal)
- `Erro → AguardaEnvio` (reprocessamento)

Não é anomalia — está no grafo da spec. O `RegraTransicaoEstado` trata-os como dois pares distintos
no mapa `De → Para`.

---

### D5 — `DescarregarDocumentoAction` adicionada (não estava na spec)

**O que aconteceu:** Durante a implementação da camada HTTP ficou evidente que o `DocumentoResource`
expõe o registo do documento mas não dá acesso ao ficheiro. Sem um endpoint de download, a API não
servia o caso de uso básico de visualizar o documento.

**Decisão:** Adicionou-se a slice `Descarregar/` com `DescarregarDocumentoAction` (ability `view`),
`DescarregarDocumentoRequest`, `FicheiroDocumentoDto` (VO de vista com `disco` e `nome`). O
Controller faz `streamDownload`. Sem alterações ao schema ou à spec de estados.

---

### D6 — Colisão de nome canónico não é verificada explicitamente

`RegraNomearProcessado` gera `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}`. Se dois
documentos do mesmo fornecedor, categoria e data forem processados, têm o mesmo nome canónico. A
operação `Storage::disk()->put()` sobrepõe silenciosamente. Limitação conhecida — não é validada
nesta issue. Impacto raro em produção (timestamp de criação distingue os registos na BD); a mitigação
(ex.: sufixo UUID) é diferida.

---

## Desvios à spec

| Referência | Tipo | Descrição |
|---|---|---|
| D1 | Adição não prevista | `ExecutorTransicaoDocumento` emergiu para evitar duplicação da mecânica de transição |
| D2 | Variante de implementação | Mover ficheiro ANTES da transação (não dentro) com compensação em falha — mesma semântica, ordem diferente |
| D3 | DTOs reformulados | `RegistarDocumentoManualDto` e `CorrigirDocumentoDto` são novos; os da #45 ficaram sem uso |
| D4 | Confirmação | Dois arcos para `AguardaEnvio` (normal + reprocessamento) — estava implícito no grafo |
| D5 | Adição não prevista | `DescarregarDocumentoAction` + endpoint `GET /documentos/{documento}/descarregar` |
| D6 | Limitação conhecida | Colisão de nome canónico não detectada — `put()` sobrepõe silenciosamente |

---

## Critérios de aceitação — verificação

| CA | Estado | Observação |
|---|---|---|
| CA-01 | ✅ | `Gate::authorize()` fora + `DB::transaction()` em todas as Actions de escrita |
| CA-02 | ✅ | `RegraTransicaoEstado` rejeita inválidas com `TransicaoInvalidaException` → 422 |
| CA-03 | ✅ | `RegraMoverFicheiro` move entre discos e actualiza `disco_storage`/`nome_ficheiro_storage` |
| CA-04 | ✅ | `TransicionarProcessadoDocumentoAction` renomeia via `RegraNomearProcessado` |
| CA-05 | ✅ | `ReprocessarDocumentoAction` aceita `ModoReprocessamento`, volta a `AguardaEnvio`, regista modo em EtapaDocumento |
| CA-06 | ✅ | `MarcarPerigosoDocumentoAction` aceita `Pendente` e `AguardaResposta` como origem |
| CA-07 | ✅ | Exactamente uma `EtapaDocumento` por transição, na mesma transação |
| CA-08 | ✅ | 4 Events `after_commit` (`DocumentoProcessado`, `DocumentoMarcadoErro`, `DocumentoMarcadoPerigoso`, `DocumentoReprocessado`) |
| CA-09 | ✅ | `ListarDocumentosAction` com `cursorPaginate` + filtro por estado + sem Repository |
| CA-10 | ✅ | Controller + rotas + FormRequests com `authorize()` + `messages()` PT |
| CA-11 | ✅ | DTOs de transição `final readonly` + invariantes no construtor + `fromRequest()` |
| CA-12 | ✅ | Testes duais por slice (Unit programático + Feature HTTP onde aplicável) |
| CA-13 | ✅ | 527 testes, 100% coverage, 100% type coverage, Larastan 9 zero erros |
| CA-14 | ✅ | `RegraMoverFicheiro` verifica retorno das operações (`throw=>false`) e faz compensação best-effort |
| CA-15 | ✅ | `TransicaoInvalidaException` convertida para 422 via `bootstrap/app.php` |

---

## Métricas

| Métrica | Valor |
|---|---|
| Testes totais | 527 |
| Cobertura | 100% |
| Type coverage | 100% |
| Erros Larastan | 0 |
| Actions novas | 13 (11 transição + Listar + Ver + Descarregar) |
| DTOs novos | 7 (`ReceberUpload`, `TransicionarProcessado`, `MarcarErro`, `MarcarPerigoso`, `Reprocessar`, `RegistarManual`, `Corrigir`, `Ficheiro`) |
| Events novos | 4 |
| Classes `Regra*` novas | 3 + 1 executor partilhado |
| Enums novos | 2 (`ModoReprocessamento`, `CampoOrdenacaoDocumentos`) |
| Endpoints HTTP novos | 8 (Listar, Criar, Upload, Ver, Corrigir, Reprocessar, Eliminar, Descarregar) |

---

## Aprendizagens

### Vertical Slice não proíbe peças internas partilhadas dentro de uma feature

O `ExecutorTransicaoDocumento` resolve uma tensão real de Vertical Slice: duplicar 30 linhas de
mecânica idêntica em 8 Actions é pior do que extrair um orquestrador interno. A regra é que a peça
partilhada viva **dentro** da feature (`Documento/Transicao/`), não como serviço cross-feature. Isto
preserva o isolamento entre features sem sacrificar DRY dentro de uma.

### DTOs são mais seguros quando modelam só o que o caller controla

Os DTOs da #45 tinham campos de storage que pertencem à Action, não ao caller. Incluí-los no DTO
criava a ilusão de que o cliente poderia fornecê-los (ou que o DTO poderia ser inválido para a Action
mesmo com dados de caller corretos). A separação `RegistarDocumentoManualDto` (campos de domínio +
ficheiro) vs. `CorrigirDocumentoDto` (só campos de domínio) demonstra que cada DTO modela exactamente
o que o caller sabe — nem mais, nem menos.

### Atomicidade filesystem↔BD é um problema de duas camadas

O `DB::transaction()` não abarca o filesystem. A abordagem "mover antes, compensar em falha" impõe
que a Action tolere estado transitório (ficheiro movido, BD não actualizada), o que só é seguro
porque o filesystem é o único escrivão naquele instante. Para consistência forte real precisaríamos
de um padrão outbox/saga — este é o trade-off consciente de "best-effort".

### `match` exaustivo em enums PHP 8.5 elimina `default` e detecta casos omissos em compile time

`RegraTransicaoEstado` usa um `match($estadoOrigem)` aninhado sem `default`. Se um novo `case` for
adicionado a `EstadoDocumento`, o Larastan 9 (exaustividade de `match`) dá erro de compilação —
impede silenciosamente que uma nova transição seja esquecida no mapa central.
