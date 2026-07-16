# Brief: refactor(laravel): unificar máquina de estados do Documento (fundir EtapaExtracao em EstadoDocumento)

**Issue:** #110
**Data:** 2026-07-16
**Branch:** refactor/unificar-maquina-estados-documento

## Contexto

O `Documento` tem hoje **duas dimensões de estado independentes**, construídas ao longo de
#90/#94/#96/#97 partindo do princípio de um **serviço remoto** de extracção:

- `EstadoDocumento` (negócio) — `Pendente → AguardaEnvio → Enviado → AguardaResposta →
Processado/Erro/Perigoso`, com mapa central em `RegraTransicaoEstado` e mecânica comum em
  `ExecutorTransicaoDocumento` (move ficheiro → `DB::transaction` → histórico → cache → evento).
- `EtapaExtracao` (extracção) — `Pendente → NecessitaOcr/TextoPronto → NecessitaCloud →
Concluido/Falhado`, vivendo em `ExtracaoDocumento.etapa_extracao` e em `EtapaDocumento.passo`
  (nullable, distingue uma linha de negócio de uma linha de IA na mesma tabela de histórico).

Essa premissa deixou de ser verdade: a extracção corre **localmente** (scan antivírus → parser
pdf → OCR → LLM local → LLM cloud — já implementados em #90/#91/#96/#97, sem orquestrador ainda).
As "etapas de extracção" **são**, na prática, o estado do documento ao longo do processamento —
não uma dimensão paralela. Os três estados `AguardaEnvio/Enviado/AguardaResposta` tornaram-se
semanticamente vazios ("enviado para onde?") e obrigam a raciocínio cruzado (ex.: um documento em
`AguardaResposta` **e** `NecessitaOcr` em simultâneo) sem ganho real — confirmado ao ler
`01-features/documento-pipeline.md` ("Modelo de 2 dimensões") e o código actual de
`RegraTransicaoEstado`/`RegraMoverFicheiro`/`ExecutorTransicaoDocumento`.

Esta issue **precede o orquestrador (#101)**: unificar a máquina de estados antes de construir o
orquestrador evita implementá-lo sobre um modelo que seria logo substituído. Os 4 novos estados
intermédios (`AnaliseTexto`/`AnaliseOcr`/`AnaliseIaLocal`/`AnaliseCloud`) mapeiam 1:1 com os 4
Commands que o orquestrador virá a expor.

## O que muda

**Decisões tomadas nesta fase de planeamento** (as duas assinaladas na issue como "a resolver no
`/planeia-issue`", confirmadas com o utilizador):

- `extracao_reclamada_em` e `extracao_tentativas` **mantêm-se em `extracoes_documento`**
  (decisão revista — a proposta inicial de os migrar para `documentos` foi reconsiderada: são
  metadados específicos do pipeline de extracção, não de negócio, e coabitam melhor com
  `texto_extraido`/`dados_json` na mesma tabela; a listagem/leitura normal de `documentos` não os
  usa, só o futuro orquestrador (#101), que pode fazer `JOIN`). `documentos` não ganha colunas
  novas nesta issue.
- **`ExtracaoDocumento` passa a ser scratch space do pipeline, não histórico** — a linha é
  eliminada (não apenas anulada) quando o `Documento` chega a `Processado`/`Erro`/`Perigoso` (ver
  "Nova invariante RGPD" abaixo). `extracoes_documento` só tem linha para documentos activamente
  em processamento; um documento terminado não tem linha, ponto final.
- `etapas_documento.passo` **é removida** (coluna + cast `EtapaExtracao`) — redundante com o
  `estado` unificado da linha de histórico. `resultado` (`ResultadoEtapa`) mantém-se: distingue
  uma tentativa de passo (`Sucesso`/`Falha`/`EmCurso`, sem mudar `estado`) de uma transição de
  negócio pura (`resultado = null`).
- Nomes de estados e de Actions ficam como propostos na issue (confirmado) — o estado é sempre um
  substantivo (`AnaliseMalware`, `AnaliseTexto`, `AnaliseOcr`, `AnaliseIaLocal`, `AnaliseCloud`) e a
  Action de transição pura mantém a família `Marcar<Estado>DocumentoAction`
  (`MarcarAnaliseMalwareDocumentoAction`, `MarcarAnaliseTextoDocumentoAction`,
  `MarcarAnaliseOcrDocumentoAction`, `MarcarAnaliseIaLocalDocumentoAction`,
  `MarcarAnaliseCloudDocumentoAction`) — coerente com a família `Marcar*` já existente
  (`MarcarErroDocumentoAction`, `MarcarPerigosoDocumentoAction`). Nenhuma destas Actions chama os
  motores de scan/OCR/IA — só transicionam o estado (excepção: a Action que corre o scan de
  malware é a actual `TriarDocumentoPendenteAction`, que já reutiliza `ContratoAnalisadorMalware` de
  #90/#91 e passa a ramificar para `AnaliseMalware→AnaliseTexto/Perigoso/Erro`; chamar os motores
  de texto/OCR/IA fica fora de âmbito desta issue — ver "O que NÃO muda").

**Enums:**

- `EstadoDocumento` (`app/Shared/Enums/EstadoDocumento.php`) — 9 cases (era 7):
  `Pendente, AnaliseMalware, AnaliseTexto, AnaliseOcr, AnaliseIaLocal, AnaliseCloud, Processado,
Erro, Perigoso`.
- `EtapaExtracao` (`app/Shared/Enums/EtapaExtracao.php`) — **removida**.
- `ResultadoEtapa` — mantida, sem alterações.

**Data model / migrations** (`database/migrations/`, `app/Models/`):

- `documentos` — sem colunas novas.
- `extracoes_documento` — remove `etapa_extracao`; índice composto `(etapa_extracao,
  extracao_reclamada_em)` simplifica para `(extracao_reclamada_em)` sozinho (detalhe de Plan).
  `extracao_reclamada_em`/`extracao_tentativas` mantêm-se. `ExtracaoDocumento` perde a
  coluna/cast/`@property-read` de `etapa_extracao`.
- `etapas_documento` — remove `passo`; `EtapaDocumento::casts()`/`@property-read`/`#[Fillable]`
  actualizados.
- Sem migração de dados de produção (schema recriado; sem dados reais — confirmado na issue).

**State objects** (`app/Shared/States/`): 9 classes em vez de 7 — `DocumentoAnaliseMalware`,
`DocumentoAnaliseTexto`, `DocumentoAnaliseOcr`, `DocumentoAnaliseIaLocal`, `DocumentoAnaliseCloud`
substituem `DocumentoAguardaEnvio`/`DocumentoEnviado`/`DocumentoAguardaResposta`.
`ContratoEstadoDocumento` (4 getters comuns) não muda de forma. `Documento::estado()` — match
exaustivo sobre os 9 casos.

**Regras de transição** (`app/Features/Documento/Transicao/`):

- `RegraTransicaoEstado` — mapa De→Para reescrito conforme o `stateDiagram-v2` da issue.
- `RegraMoverFicheiro::discoParaEstado()` — mapa actualizado (`AnaliseMalware/AnaliseTexto/
AnaliseOcr → entrada`; `AnaliseIaLocal/AnaliseCloud → enviado`).
- `ExecutorTransicaoDocumento` — mecânica interna **não muda**.

**Actions de transição:**

- Removidas: `MarcarEnviadoDocumentoAction`, `MarcarAguardaRespostaDocumentoAction` (dirs + testes).
- Renomeada: `MarcarAguardaEnvioDocumentoAction` → `MarcarAnaliseTextoDocumentoAction`.
- Novas: `MarcarAnaliseMalwareDocumentoAction` (`Pendente→AnaliseMalware`),
  `MarcarAnaliseOcrDocumentoAction`, `MarcarAnaliseIaLocalDocumentoAction`,
  `MarcarAnaliseCloudDocumentoAction`. Mesma estrutura da família `Marcar*` actual (`final
readonly`, injecta `ExecutorTransicaoDocumento`, sem `Gate::authorize`).
- Ajustadas (grafo De→Para, mecânica intacta): `TransicionarProcessadoDocumentoAction`,
  `MarcarErroDocumentoAction`, `MarcarPerigosoDocumentoAction`, `ReprocessarDocumentoAction`
  (`Erro→Pendente`; deixa de precisar de tocar em `ExtracaoDocumento` de forma condicional —
  a linha já foi eliminada ao entrar em `Erro`, ver "Nova invariante RGPD" abaixo; fica mais
  simples do que é hoje, não mais complexa), `TriarDocumentoPendenteAction`/
  `ReivindicarDocumentoPendenteAction` (reivindica `Pendente`, admite para `AnaliseMalware` via
  `MarcarAnaliseMalwareDocumentoAction`, depois corre o scan e ramifica para
  `AnaliseTexto`/`Perigoso`/`Erro`).
- `RegistarEtapaExtracaoAction`/`RegistarEtapaExtracaoDto` — deixa de escrever `etapa_extracao`
  (removida); `Dto` perde o campo `etapaExtracao` (redundante — o passo é o `estado` actual do
  documento). Continua só a escrever em `extracoes_documento` + `EtapaDocumento` — não passa a
  tocar em `documentos` (decisão de lease/tentativas revertida, ver acima).

**Nova invariante RGPD — `ExtracaoDocumento` é eliminada ao entrar num estado terminal:**

- `extracoes_documento` só faz sentido **enquanto o documento está a ser processado** — ao chegar
  a `Processado` (os campos de domínio relevantes já foram copiados para `documentos` por
  `TransicionarProcessadoDocumentoAction`), `Erro` ou `Perigoso` (a tentativa de extracção não vai
  continuar), a linha inteira (`texto_extraido`, `dados_json`, `extracao_reclamada_em`,
  `extracao_tentativas`) deixa de ter função. Em vez de anular só os campos PII, **a linha é
  eliminada** (`delete()`, não `update()` para `null`) — fronteira mais limpa: documento em
  pipeline tem linha, documento terminado não tem. CA nova a acrescentar ao âmbito da issue:
  **eliminação de `ExtracaoDocumento` ao entrar em qualquer um dos 3 estados terminais**,
  minimização de dados por design (RGPD), alinhado com "Campos sensíveis não são logados em claro"
  do CLAUDE.md.
- Efeito em `ReprocessarDocumentoAction` (`Erro→Pendente`): a linha já foi eliminada ao entrar em
  `Erro` pela invariante acima — a Action deixa de precisar de `update()` condicional nem de
  transacção própria com `SAVEPOINT`; um `delete()` defensivo (idempotente, cobre o caso raro de a
  linha ainda existir) chega como rede de segurança.

**Consumidores:**

- `DocumentoResource` — remove o campo `etapa_extracao` (CA-07).
- `ReconciliarFicheirosJob`/`Documento::scopeWherePresos()` — lista de estados transitórios passa
  a `AnaliseMalware/AnaliseTexto/AnaliseOcr/AnaliseIaLocal/AnaliseCloud` (5 em vez de 3).
- `DocumentoFactory`, `ExtracaoDocumentoFactory`, `EtapaDocumentoFactory` + todos os testes que
  usam os estados/factories antigos (Unit + Feature, padrão dual).

**system_spec** (`docs/system_spec/`): `02-shared/estados.md`, `02-shared/enums.md`,
`01-features/documento-pipeline.md` (secção "Modelo de 2 dimensões" removida/reescrita),
`03-models/documento.md`, `03-models/extracao-documento.md`, `03-models/etapa-documento.md`,
`04-infra/queue-jobs.md`, `00-index.md`.

## O que NÃO muda

- `app/Infrastructure/AI` e `app/Infrastructure/Extracao` — os motores devolvem
  `ResultadoExtracao`/`ResultadoExtracaoIA`, que não referem estados; sem alteração.
- O scan de malware (#90/#91) e os extractores/cliente IA (#96/#97) — comportamento inalterado,
  só os estados de destino das Actions que os invocam mudam de nome.
- `ExecutorTransicaoDocumento` — mecânica interna (ordem mover→transação→histórico→cache→evento,
  compensação em falha) fica igual; só o que ele consome (`RegraTransicaoEstado`,
  `RegraMoverFicheiro`) muda — a eliminação de `ExtracaoDocumento` nos 3 terminais é a única
  adição de comportamento (ver "Nova invariante RGPD" acima), decisão de onde implementar (dentro
  do Executor de forma genérica vs. em cada Action terminal) fica para a Spec/Plan.
- Construção do orquestrador real / Commands `extracao:*` / chamada aos motores de Infrastructure —
  isso é a issue #101, que esta issue **bloqueia** mas não implementa.
- Nenhuma rota HTTP nova ou alterada — refactor interno, sem superfície de ataque nova.
- Registo manual (`RegistarDocumentoManualAction`) continua a criar directo em
  `Processado`/`Perigoso`/`Erro` sem passar pelo pipeline — sem uso de `RegraTransicaoEstado`.

## Riscos identificados

- **Fusão de duas dimensões numa só aumenta a área de código tocada por transição** — cada
  transição de estado deixa de ser "só negócio" (`ExecutorTransicaoDocumento`) ou "só extracção"
  (`RegistarEtapaExtracaoAction`) isoladamente; passa a existir um ponto de intersecção real entre
  as duas camadas (a eliminação de `ExtracaoDocumento` acontece dentro do fluxo das 3 Actions que
  levam a um terminal, tocando uma tabela que até agora só `RegistarEtapaExtracaoAction` escrevia).
  Mais um ponto de intersecção = mais combinações a testar (padrão dual Unit+Feature em cada Action
  tocada) e maior probabilidade de um caso de borda ficar por cobrir só nesta issue.
- **Superfície de testes grande** — todas as Actions de transição têm padrão dual (Unit +
  Feature); renomear/remover/criar 9 Actions implica tocar em ~18+ ficheiros de teste só nesta
  camada, mais os testes de `RegraTransicaoEstado`, `RegraMoverFicheiro`,
  `ReconciliarFicheirosJob`, `DocumentoFactory`/`ExtracaoDocumentoFactory`/`EtapaDocumentoFactory`
  e os testes de concorrência de `ReivindicarDocumentoPendenteAction` (duas conexões MySQL reais,
  `07-testing.md`). Larastan 9 (match exaustivo sem `default`) vai apanhar omissões em
  `RegraTransicaoEstado`/`RegraMoverFicheiro`/`Documento::estado()`, mas não em código de teste que
  passe um `EstadoDocumento` antigo — risco de testes obsoletos ficarem a referenciar cases
  removidos (`AguardaEnvio`/`Enviado`/`AguardaResposta`) e falharem só em runtime.
- **`ReprocessarDocumentoAction` simplifica, mas exige cuidado na ordem de implementação** — hoje
  abre a própria `DB::transaction()` (para fazer `ExtracaoDocumento::update()` depois da
  transição, via `SAVEPOINT`); com a linha já eliminada ao entrar em `Erro` (nova invariante
  RGPD), a Action deixa de precisar de tocar em `ExtracaoDocumento` de forma condicional — um
  `delete()` defensivo idempotente é suficiente. Risco: se a eliminação em `MarcarErroDocumentoAction`
  não estiver implementada/testada primeiro, um teste de `ReprocessarDocumentoAction` pode passar
  "por acaso" sem cobrir o caso em que a linha ainda existe.
- **`RegistarEtapaExtracaoAction` mantém-se sem alteração de forma** — continua só a escrever em
  `extracoes_documento` (agora sem `etapa_extracao`) + `EtapaDocumento`, na mesma transacção;
  não passa a tocar em `documentos` (decisão de lease/tentativas revertida). Continua a não usar
  `RegraTransicaoEstado` (não muda `estado`).
- **Larastan 9 / 100% coverage e type-coverage** — o volume de ficheiros novos/alterados é grande;
  qualquer `match` não exaustivo sobre o novo `EstadoDocumento` (9 cases) falha imediatamente, o
  que é o comportamento desejado (é a garantia da issue), mas aumenta a superfície onde isso pode
  acontecer (qualquer `match`/`switch` esquecido em código ou teste).
- **`ReconciliarFicheirosJob`** — a lista de estados transitórios passa de 3 para 5
  (`AnaliseMalware/AnaliseTexto/AnaliseOcr/AnaliseIaLocal/AnaliseCloud`); o índice
  `(estado, updated_at)` em `documentos` já existe e cobre isto, mas o teste que verifica quais
  estados são "presos" precisa de cobrir os 5.
- **WRN-022 pendente** (`docs/process-warnings.md`) — `checkpoint:scan` no fecho da Fase 2 pode
  repetir os mesmos 4 WARNs não relacionados com esta issue (config ambiente, `.gitignore`, vendor
  autoload, supply chain tooling); não é um risco novo, mas fica registado para não gerar alarme
  falso no fecho desta issue.

## Questões em aberto

Nenhuma — as duas decisões explicitamente marcadas pela issue como "a resolver no
`/planeia-issue`" (localização de `extracao_reclamada_em`/`extracao_tentativas`; destino da coluna
`etapas_documento.passo`) foram confirmadas com o utilizador antes de escrever este Brief (ver "O
que muda"). A nomenclatura proposta na issue foi confirmada sem alterações.
