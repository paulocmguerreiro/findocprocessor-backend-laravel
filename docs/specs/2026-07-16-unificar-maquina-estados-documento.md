# Spec: refactor(laravel): unificar máquina de estados do Documento (fundir EtapaExtracao em EstadoDocumento)

**Issue:** #110
**Brief:** docs/briefs/2026-07-16-unificar-maquina-estados-documento.md
**Data:** 2026-07-16

## Requisitos funcionais

- RF-01: `EstadoDocumento` passa a ter 9 cases (era 7): `Pendente, AnaliseMalware, AnaliseTexto,
  AnaliseOcr, AnaliseIaLocal, AnaliseCloud, Processado, Erro, Perigoso` — cobre o pipeline
  completo `Pendente → AnaliseMalware → AnaliseTexto/AnaliseOcr → AnaliseIaLocal → AnaliseCloud →
  Processado/Erro/Perigoso`.
- RF-02: `EtapaExtracao` é removido de `app/Shared/Enums/`. `ResultadoEtapa` mantém-se sem
  alterações.
- RF-03: O grafo de transições permitidas (`RegraTransicaoEstado::transicoesPermitidas()`, match
  exaustivo sem `default`) reflecte exactamente o `stateDiagram-v2` da issue:
  - `Pendente → AnaliseMalware`
  - `AnaliseMalware → AnaliseTexto | Perigoso | Erro`
  - `AnaliseTexto → AnaliseIaLocal | AnaliseOcr | Erro`
  - `AnaliseOcr → AnaliseIaLocal | Erro`
  - `AnaliseIaLocal → Processado | AnaliseCloud | Perigoso | Erro`
  - `AnaliseCloud → Processado | Erro | Perigoso`
  - `Erro → Pendente`
  - `Processado → Processado` (self-loop, correcção)
  - `Perigoso →` (terminal, sem saída)
  Qualquer par fora deste mapa continua a lançar `TransicaoInvalidaException` (→ 422).
- RF-04: `RegraMoverFicheiro::discoParaEstado()` mapeia os 9 estados aos 5 discos existentes —
  `Pendente/AnaliseMalware/AnaliseTexto/AnaliseOcr → entrada`; `AnaliseIaLocal/AnaliseCloud →
  enviado`; `Processado → processado`; `Erro → erro`; `Perigoso → perigoso`. Nenhum disco novo.
- RF-05: Cada passo intermédio do pipeline tem uma Action de transição própria, família
  `Marcar<Estado>DocumentoAction`, mesma estrutura da família `Marcar*` actual (`final readonly`,
  injecta `ExecutorTransicaoDocumento`, sem `Gate::authorize`):
  `MarcarAnaliseMalwareDocumentoAction` (`Pendente→AnaliseMalware`),
  `MarcarAnaliseTextoDocumentoAction` (renomeada de `MarcarAguardaEnvioDocumentoAction`,
  `AnaliseMalware→AnaliseTexto`), `MarcarAnaliseOcrDocumentoAction` (`AnaliseTexto→AnaliseOcr`),
  `MarcarAnaliseIaLocalDocumentoAction` (`AnaliseTexto|AnaliseOcr→AnaliseIaLocal`),
  `MarcarAnaliseCloudDocumentoAction` (`AnaliseIaLocal→AnaliseCloud`). Nenhuma delas chama motores
  de scan/OCR/IA — só transicionam o `estado` (ver RN-06 para a excepção de `AnaliseMalware`).
  `MarcarEnviadoDocumentoAction` e `MarcarAguardaRespostaDocumentoAction` são removidas (dirs +
  testes).
- RF-06: `extracao_reclamada_em` e `extracao_tentativas` mantêm-se em `extracoes_documento`
  (decisão revista — ver "Questões resolvidas"). `documentos` não ganha colunas novas nesta issue.
  O índice composto existente `(etapa_extracao, extracao_reclamada_em)` simplifica para
  `(extracao_reclamada_em)` (coluna removida do índice, não a coluna de lease).
- RF-07: `etapas_documento.passo` (coluna + cast `EtapaExtracao`) é removida. `resultado`
  (`ResultadoEtapa`) mantém-se — distingue uma linha de tentativa de passo (`resultado` preenchido,
  sem mudar `estado`) de uma linha de transição de negócio pura (`resultado = null`).
- RF-08: `DocumentoResource` deixa de expor o campo `etapa_extracao` no `toArray()`.
- RF-09: Ao `Documento` entrar em `Processado`, `Erro` ou `Perigoso`, a linha correspondente em
  `ExtracaoDocumento` (se existir) é **eliminada** (`delete()`, não anulada) na mesma transacção da
  transição — `extracoes_documento` só tem linha para documentos activamente em pipeline.
  Documentos sem linha de `ExtracaoDocumento` (ex.: registo manual, falha de scan em
  `Pendente`/`AnaliseMalware` antes de qualquer registo de extracção) não são afectados (nada a
  eliminar).
- RF-10: `ReprocessarDocumentoAction` (`Erro → Pendente`) deixa de precisar de resetar
  `ExtracaoDocumento` de forma condicional — a linha já foi eliminada ao entrar em `Erro` (RF-09).
  Mantém, por segurança, um `delete()` defensivo idempotente (cobre o caso raro de a linha ainda
  existir); deixa de abrir `DB::transaction()` própria só para isto.
- RF-11: `ReivindicarDocumentoPendenteAction`/`TriarDocumentoPendenteAction` passam a operar
  `Pendente → AnaliseMalware` (via `MarcarAnaliseMalwareDocumentoAction`) e, dentro da mesma
  transacção/lock, correr o scan de malware existente e ramificar `AnaliseMalware →
  AnaliseTexto|Perigoso|Erro` (mesma lógica de decisão actual — infectado/limpo/desligado/falha —
  só os estados de destino mudam de nome).
- RF-12: `RegistarEtapaExtracaoDto` perde o campo `etapaExtracao` (redundante — o passo é sempre o
  `estado` actual do `Documento`). `RegistarEtapaExtracaoAction` mantém-se sem alteração de forma —
  continua só a fazer upsert de `extracoes_documento` (agora sem `etapa_extracao`) e a gravar a
  `EtapaDocumento`, na mesma transacção; não passa a tocar em `documentos`. Continua sem
  `Gate::authorize` e sem usar `RegraTransicaoEstado` (não muda `estado`).
- RF-13: `Documento::scopeWherePresos()` (consumido por `ReconciliarFicheirosJob`) passa a
  considerar os 5 estados transitórios (`AnaliseMalware`, `AnaliseTexto`, `AnaliseOcr`,
  `AnaliseIaLocal`, `AnaliseCloud`) em vez dos 3 actuais.

## Requisitos não funcionais

- RNF-01: `declare(strict_types=1)` em todos os ficheiros alterados/criados.
- RNF-02: Larastan nível 9, zero erros — em particular, todo `match`/`switch` que consome
  `EstadoDocumento` (`RegraTransicaoEstado`, `RegraMoverFicheiro::discoParaEstado()`,
  `Documento::estado()`, `RegistarDocumentoManualAction::avaliarScan()`) é exaustivo sem `default`.
- RNF-03: 100% code coverage e 100% type coverage (`composer test`).
- RNF-04: Padrão dual de testes (Unit + Feature) mantido/actualizado para todas as Actions tocadas
  (novas, renomeadas, removidas ou com grafo ajustado), incluindo o teste de concorrência real
  (duas conexões MySQL) de `ReivindicarDocumentoPendenteAction`.
- RNF-05: Sem migração de dados de produção — schema recriado via migrations, sem dados reais
  (confirmado na issue).
- RNF-06: Sem alteração de rotas HTTP nem de superfície de autenticação/autorização — refactor
  interno de domínio.

## Contratos de API

Sem rotas novas nem alteradas. Delta de schema (resposta existente, mesma rota
`GET /api/documentos/{documento}` e listagem): `DocumentoResource` deixa de incluir o campo
`etapa_extracao` (RF-08) — o `estado` unificado já exprime a informação. `./openapi.yaml` é
actualizado na Fase 3a (`/documenta-implementacao`), não nesta Spec.

## Modelo de dados

### `documentos`

Sem alterações de coluna nesta issue.

### `extracoes_documento` (remove `etapa_extracao`; resto mantém-se)

| Campo removido | Notas |
| --------------- | ----- |
| `etapa_extracao` | Cast `EtapaExtracao` (enum removido); índice composto `(etapa_extracao, extracao_reclamada_em)` simplifica para `(extracao_reclamada_em)` |

Fica com: `id`, `id_documento` (unique, 1-1), `extracao_reclamada_em`, `extracao_tentativas`,
`texto_extraido`, `dados_json`, `created_at`/`updated_at` — **sem alteração de forma face ao
schema actual**, só perde a coluna `etapa_extracao`.

**Ciclo de vida da linha (novo, RF-09):** criada lazily pela primeira chamada a
`RegistarEtapaExtracaoAction` (upsert, como já acontece hoje); eliminada (`delete()`) quando o
`Documento` correspondente entra em `Processado`/`Erro`/`Perigoso`. Um documento fora do pipeline
activo não tem linha em `extracoes_documento`.

### `etapas_documento` (remove)

| Campo removido | Notas |
| --------------- | ----- |
| `passo` | Cast `EtapaExtracao` (enum removido); redundante com `estado` da própria linha |

Fica com: `id`, `id_documento`, `estado`, `resultado`, `motivo`, `id_utilizador`, `created_at`.

## Regras de negócio

- RN-01: Toda a mudança de `Documento.estado` passa por uma Action de transição +
  `RegraTransicaoEstado` — nunca `if ($doc->estado == ...)` (já vigente, reafirmado com o novo
  grafo de 9 estados).
- RN-02: O movimento de disco `entrada → enviado` ocorre exactamente na transição para
  `AnaliseIaLocal` (era ao entrar em `Enviado`) — é o momento em que o texto "é enviado" para o
  LLM local.
- RN-03: A eliminação de `ExtracaoDocumento` (RF-09) é accionada automaticamente pelas 3 Actions
  que levam a um estado terminal (`TransicionarProcessadoDocumentoAction`,
  `MarcarErroDocumentoAction`, `MarcarPerigosoDocumentoAction`) — não depende do chamador
  lembrar-se de a invocar explicitamente. Local exacto da implementação (dentro de
  `ExecutorTransicaoDocumento` de forma genérica, condicionado a `$novoEstado` ser terminal, vs.
  em cada uma das 3 Actions) é decisão de Plan; o requisito verificável é o efeito, não o sítio.
- RN-04: Registo manual (`RegistarDocumentoManualAction`) continua a criar o `Documento` directo
  em `Processado`/`Perigoso`/`Erro`, sem passar por `RegraTransicaoEstado` — RN-03 não se aplica
  aqui porque nunca existe `ExtracaoDocumento` para um documento criado manualmente.
- RN-05: `extracao_reclamada_em`/`extracao_tentativas` mantêm-se em `extracoes_documento`, campos
  passivos nesta issue — só o Model os expõe; a reivindicação real com `lockForUpdate()`/TTL e a
  transição automática ao esgotar tentativas ficam para o orquestrador (#101), tal como já
  documentado para o modelo anterior. Como a linha é eliminada ao chegar a um terminal (RF-09),
  estes campos só existem, na prática, enquanto o documento está em pipeline activo.
- RN-06: `AnaliseMalware` é o único estado intermédio cuja Action associada
  (`TriarDocumentoPendenteAction`, chamada a partir de `ReivindicarDocumentoPendenteAction`) já
  invoca um motor real (`ContratoAnalisadorMalware`, #90/#91) — porque essa ligação já existia
  antes desta issue. `AnaliseTexto`/`AnaliseOcr`/`AnaliseIaLocal`/`AnaliseCloud` ficam sem chamada
  a motor nesta issue (scaffolding puro); ligar `ExtractorTextoNativo`/`ExtractorOcr`/
  `ClienteExtracaoIAPrism` é âmbito exclusivo do orquestrador (#101).

## Dependências

- Issues bloqueantes: nenhuma (consolida #90/#94/#96/#97, já merged).
- Bloqueia: #101 (orquestrador de pipeline).

## Questões resolvidas

| Questão (do Brief / levantada no planeamento) | Decisão |
| --- | --- |
| Onde vivem `extracao_reclamada_em`/`extracao_tentativas`? | **Mantêm-se em `extracoes_documento`** (decisão revista após reconsideração — proposta inicial de migrar para `documentos` foi abandonada: são metadados de pipeline, coabitam melhor com `texto_extraido`/`dados_json`; a listagem normal de `documentos` não os usa; RF-06). |
| Destino de `etapas_documento.passo`? | Removida (RF-07) — redundante com o `estado` unificado da própria linha de histórico; `resultado` mantém-se. |
| Nomenclatura das Actions de transição intermédias — `Marcar<Estado>DocumentoAction` (estado como substantivo) ou `Analisar<Passo>Action` (verbo)? | Família `Marcar<Estado>DocumentoAction`, estado como substantivo (`AnaliseMalware`, `AnaliseTexto`, etc.) — coerente com a família `Marcar*` já existente; nenhuma delas chama motores (RF-05, RN-06). |
| O payload PII (`texto_extraido`/`dados_json`) deve sobreviver à chegada a um estado terminal? | Não. **Decisão revista**: em vez de anular só os campos PII, a linha inteira de `ExtracaoDocumento` é eliminada (RF-09/RN-03) — `extracoes_documento` passa a ser scratch space do pipeline, só existe enquanto o documento está activamente a ser processado; simplifica `ReprocessarDocumentoAction` (RF-10) como efeito colateral. |

## Critérios de aceitação

- [ ] CA-01: existe um único enum de estado; `EtapaExtracao` removido; `ResultadoEtapa` mantido. *(issue)*
- [ ] CA-02: `documentos.estado` contém o estado unificado; `extracoes_documento` sem
      `etapa_extracao`, com `texto_extraido`/`dados_json` intactos (PII isolada). *(issue)*
- [ ] CA-03: cada passo do pipeline tem estado próprio + Action de transição própria; toda a
      mudança de estado passa por `RegraTransicaoEstado` (match exaustivo sem `default`); nunca
      `if ($doc->estado == ...)`. *(issue)*
- [ ] CA-04: grafo de transições reescrito conforme o diagrama; movimento de disco
      `entrada→enviado` ao entrar em `AnaliseIaLocal`. *(issue)*
- [ ] CA-05: registo manual continua a ir directo a `Processado` (scan inline, sem entrar no
      pipeline); scan pode mandar para `Perigoso`/`Erro`. *(issue)*
- [ ] CA-06: `ReprocessarDocumentoAction` reinicia o pipeline (`Erro → Pendente`) e reseta a
      extracção; `CorrigirDocumentoAction` mantém o self-loop `Processado → Processado`. *(issue)*
- [ ] CA-07: `DocumentoResource` deixa de expor `etapa_extracao` separado; nunca expõe PII. *(issue)*
- [ ] CA-08: **diagrama Mermaid `stateDiagram-v2`** presente em `02-shared/estados.md`
      (consultável no GitHub), a acompanhar a tabela estado→disco. *(issue)*
- [ ] CA-09: `composer test` verde (Larastan L9, coverage/type-coverage 100%). *(issue)*
- [ ] CA-10: system_spec actualizada — `02-shared/estados.md`, `02-shared/enums.md`,
      `01-features/documento-pipeline.md` (secção "2 dimensões" removida/reescrita),
      `03-models/extracao-documento.md`, `03-models/etapa-documento.md`,
      `04-infra/queue-jobs.md` + `00-index.md`. *(issue)*
- [ ] CA-11: ao transicionar para `Processado`, `Erro` ou `Perigoso`, a linha de
      `ExtracaoDocumento` (quando existe) é **eliminada**; testado para os 3 estados terminais e
      para o caso sem linha de `ExtracaoDocumento` (não falha, não cria linha). *(spec)*
- [ ] CA-12: `extracao_reclamada_em`/`extracao_tentativas` mantêm-se em `extracoes_documento`;
      `documentos` sem colunas novas; índice `(etapa_extracao, extracao_reclamada_em)` simplifica
      para `(extracao_reclamada_em)`. *(spec)*
- [ ] CA-13: `etapas_documento.passo` removida (coluna + cast); `resultado` mantém-se sem
      alterações de comportamento. *(spec)*

## SYSTEM_SPEC a actualizar

- `docs/system_spec/02-shared/estados.md` — ciclo de estados (9 cases), semântica, mapeamento
  estado→disco, diagrama Mermaid `stateDiagram-v2` (CA-08).
- `docs/system_spec/02-shared/enums.md` — `EstadoDocumento` (9 cases); remover secção
  `EtapaExtracao`.
- `docs/system_spec/01-features/documento-pipeline.md` — remover secção "Modelo de 2 dimensões";
  reescrever tabela de Actions de transição, mapa De→Para, recorder de extracção
  (`RegistarEtapaExtracaoAction` sem `etapaExtracao`), nova secção sobre a eliminação de
  `ExtracaoDocumento` nos terminais (RN-03).
- `docs/system_spec/03-models/documento.md` — sem alteração de colunas; rever apenas o texto que
  referia o antigo modelo de 2 dimensões.
- `docs/system_spec/03-models/extracao-documento.md` — tabela sem `etapa_extracao`; nova secção
  "Ciclo de vida da linha" (criada lazily, eliminada nos 3 terminais); Model/Factory actualizados.
- `docs/system_spec/03-models/etapa-documento.md` — tabela sem `passo`, Model/Factory
  actualizados.
- `docs/system_spec/04-infra/queue-jobs.md` — `ReconciliarFicheirosJob` com os 5 estados
  transitórios.
- `docs/system_spec/00-index.md` — sem ficheiro novo a acrescentar nesta issue (todos os ficheiros
  tocados já constam do índice); confirmar que nenhuma linha fica desactualizada.

## Verificação RGPD/NIS2

- Dados pessoais: `texto_extraido`/`dados_json` (PII) continuam isolados em `extracoes_documento`,
  fora do audit trail e nunca em Resource. Esta issue **reforça** a minimização de dados: a linha
  inteira que os contém é eliminada assim que o documento sai do pipeline activo (RF-09) — o
  payload PII só "vive" enquanto o documento está de facto a ser processado, não apenas os campos
  isolados.
- Superfície de ataque: inalterada (refactor interno; sem novos endpoints, sem alteração de
  autenticação/autorização).
