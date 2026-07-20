# Spec: reorganiza estrutura de pastas da feature Documento (WRN-037)

**Issue:** #114
**Brief:** docs/briefs/2026-07-20-reorganiza-pastas-documento.md
**Data:** 2026-07-20

> Refactor estrutural puro. Sem contratos de API, sem modelo de dados, sem lógica nova. Os requisitos
> abaixo descrevem o **estado-alvo da árvore de ficheiros** e as invariantes de "sem mudança de
> comportamento". Aplica a regra de granularidade de
> `docs/system_spec/02-shared/estrutura-subpastas-features.md` ao código real.

## Requisitos funcionais

- **RF-01:** Toda a Action com <3 artefactos próprios em `app/Features/Documento/` fica como ficheiro
  solto na subpasta semântica pai (não em pasta própria):
  - `Pesquisa/Ver/{VerDocumentoAction,VerDocumentoRequest}.php` → `Pesquisa/` (2 artefactos, solto).
  - `Atribuicao/Reivindicar/ReivindicarDocumentoPendenteAction.php` → `Atribuicao/` (1, solto).
  - `Atribuicao/ReivindicarDocumentoEmEtapa/ReivindicarDocumentoEmEtapaAction.php` → `Atribuicao/` (1).
  - `Processamento/ProcessarAnalise{Texto,Ocr,IaLocal,Cloud}/*Action.php` → `Processamento/` (1 cada).
  - `Processamento/RegistarEtapaExtracao/{Action,Dto}.php` → `Processamento/` (2, solto).
  - `Processamento/RegistarFalhaTecnicaExtracao/*Action.php` → `Processamento/` (1).
- **RF-02:** `Processamento/ReconciliarEntidades/` funde em `Processamento/ConcluirExtracao/` — a pasta
  `ConcluirExtracao/` passa a conter 3 ficheiros (`ConcluirExtracaoDocumentoAction`,
  `RegraReconciliarEntidadesDocumento`, `ResultadoReconciliacaoEntidades`) e mantém-se como pasta
  própria (atinge o limiar de 3). A pasta `ReconciliarEntidades/` é removida.
- **RF-03:** `Atribuicao/Triar/TriarDocumentoPendenteAction` é **renomeada** para
  `ProcessarAnaliseMalwareDocumentoAction` e movida para `Processamento/` (ficheiro solto). O nome da
  classe, o namespace, o ficheiro de teste unitário homónimo
  (`TriarDocumentoPendenteActionTest` → `ProcessarAnaliseMalwareDocumentoActionTest`) e as referências
  textuais em comentários noutras Actions são actualizados. A intenção original ("triagem de malware",
  RN-06) mantém-se legível nos comentários onde fizer sentido.
- **RF-04:** Cria-se `app/Features/Documento/Operacoes/TransicoesEstado/` (subpasta aninhada, flat)
  contendo, como ficheiros soltos, as 8 Actions de transição de estado/etapa (mais os seus DTOs):
  `MarcarAnaliseTextoDocumentoAction`, `MarcarAnaliseOcrDocumentoAction`,
  `MarcarAnaliseIaLocalDocumentoAction`, `MarcarAnaliseCloudDocumentoAction`,
  `MarcarAnaliseMalwareDocumentoAction` (as 5 de `Processamento/`), `MarcarErroDocumentoAction`+`Dto`,
  `MarcarPerigosoDocumentoAction`+`Dto` (da raiz da Feature), e
  `TransicionarProcessadoDocumentoAction`+`Dto` (de `Operacoes/TransicionarProcessado/`). As pastas de
  origem esvaziadas (`MarcarErro/`, `MarcarPerigoso/`, `Operacoes/TransicionarProcessado/`, e as 5
  `Processamento/MarcarAnalise*/`) são removidas.
- **RF-05:** `Atribuicao/` mantém-se subpasta com as 2 `Reivindicar*` soltas — **não** dissolve para a
  raiz da Feature, apesar de <3 (decisão explícita da issue, permitida pela regra de dissolução
  não-automática abaixo do limiar).
- **RF-06:** Todos os consumidores das classes movidas/renomeadas passam a referenciá-las pelo novo
  FQN: `DocumentoController`, os 5 Console Commands em `app/Console/Commands/Extracao/`, os
  cross-imports entre Actions da feature, e os ~24 testes unitários em
  `tests/Unit/Features/Documento/` que as importam por FQN.

## Requisitos não funcionais

- **RNF-01:** Zero alteração de comportamento — cada classe passa exactamente nos mesmos testes que já
  passava. Nenhuma assinatura de método, corpo de método ou contrato público muda.
- **RNF-02:** Namespace de cada ficheiro movido corresponde ao novo caminho físico (PSR-4). Nenhum
  `use` órfão ou não usado remanescente (Larastan nível 9 apanha imports não usados).
- **RNF-03:** Os ficheiros de teste **não mudam de directório** (a pasta `tests/Unit/Features/Documento/`
  é flat) — só os `use` mudam; excepção única: o rename de `TriarDocumentoPendenteActionTest.php`.
- **RNF-04:** Refactor entregue em commits isolados por CA (sem lógica nova misturada), conforme o
  "Fluxo ao mover uma Action".

## Contratos de API (se aplicável)

Não aplicável — nenhuma rota, verbo, schema ou `openapi.yaml` muda. Só localização interna de classes.

## Modelo de dados (se aplicável)

Não aplicável — nenhuma migration, coluna ou relação Eloquent muda.

## Regras de negócio

Nenhuma regra de negócio nova ou alterada. As invariantes de domínio existentes (`RegraTransicaoEstado`,
RN-06, etc.) permanecem no mesmo código, apenas com namespace diferente.

## Dependências

- Issues bloqueantes: nenhuma. WRN-037 (commit `bf0210e`) já fixou a regra de granularidade; esta issue
  só a aplica.

## Questões resolvidas

| Questão (do Brief) | Decisão (Checkpoint A, 2026-07-20) |
| ------------------ | ---------------------------------- |
| CA-04 agrupa em `Marcacoes` ou respeita a nota "Anomalias→Processamento" do spec? | Issue #114 vence: os 8 atalhos consolidam-se todos juntos; a nota "Anomalias"/estado-alvo do spec é apagada/reescrita na CA-06 e as `MarcarAnalise*` saem do vocabulário `Processamento/`. |
| Nome da subpasta nova? | `TransicoesEstado/` (não `Marcacoes`). Descreve a intenção (transição de estado/etapa), não o mecanismo ("marcar"); distingue-se de `Operacoes/Transicao/` (o motor). |

## Critérios de aceitação

> Herdados da issue — CAs originais preservados. CA-04 e CA-06 ajustados só no nome da pasta
> (`Marcacoes` → `TransicoesEstado`), decisão de Checkpoint A.

- [ ] CA-01: aplicar a regra de granularidade (Action <3 artefactos = ficheiro solto) às 9 pastas
  listadas (`Pesquisa/Ver`, 2× `Atribuicao/Reivindicar*`, 4× `Processamento/ProcessarAnalise*`,
  `Processamento/RegistarEtapaExtracao`, `Processamento/RegistarFalhaTecnicaExtracao`). *(issue)*
- [ ] CA-02: fundir `Processamento/ReconciliarEntidades/` em `Processamento/ConcluirExtracao/` (3
  ficheiros). *(issue)*
- [ ] CA-03: renomear+mover `Atribuicao/Triar/TriarDocumentoPendenteAction` →
  `Processamento/ProcessarAnaliseMalwareDocumentoAction`; actualizar referências a "triagem"/RN-06 em
  comentários onde fizer sentido. *(issue)*
- [ ] CA-04: criar `Operacoes/TransicoesEstado/` (8 Actions soltas) e mover para lá as 5
  `MarcarAnalise*`, `MarcarErro`+Dto, `MarcarPerigoso`+Dto, `TransicionarProcessado`+Dto. *(issue —
  nome da pasta ajustado em Checkpoint A)*
- [ ] CA-05: `Atribuicao/` mantém as 2 `Reivindicar*` soltas, sem dissolver para a raiz. *(issue)*
- [ ] CA-06: seguir o "Fluxo ao mover uma Action" em cada movimento (namespace, imports, grep de
  referências no `docs/system_spec/`, sem alteração de comportamento). *(issue)*
- [ ] CA-07: `composer test` verde no fim (100% coverage/types, arch, Larastan nível 9 zero erros).
  *(issue)*
- [ ] CA-08: nenhum `use` órfão nem referência textual ao nome antigo `TriarDocumentoPendenteAction`
  remanescente em código (grep limpo, exceptuando comentários históricos deliberados). *(spec)*

## SYSTEM_SPEC a actualizar

> Escrita efectiva na Fase 3a (`/documenta-implementacao`). Aqui só se declara o delta.

- `docs/system_spec/01-features/documento.md` — caminhos/namespaces das Actions movidas.
- `docs/system_spec/01-features/documento-pipeline.md` — namespaces do pipeline (`Processar*`,
  `Marcar*`, `ConcluirExtracao`, `RegistarEtapa*`).
- `docs/system_spec/01-features/documento-reconciliacao.md` — `ReconciliarEntidades` agora em
  `ConcluirExtracao/`.
- `docs/system_spec/02-shared/estrutura-subpastas-features.md` — **reconciliação**: converter o
  "Exemplo real validado" de estado-alvo para estado real; apagar/reescrever as notas "Anomalias" e a
  linha de vocabulário que lista `MarcarAnalise*` sob `Processamento/`, reflectindo
  `Operacoes/TransicoesEstado/`.
- `docs/system_spec/03-models/extracao-documento.md`, `04-infra/malware.md`, `04-infra/queue-jobs.md`,
  `04-infra/transactions.md` — actualizar quaisquer caminhos/namespaces referenciados.

## Verificação RGPD/NIS2

- Dados pessoais: nenhum dado novo, nenhuma alteração ao que é processado/armazenado.
- Superfície de ataque: inalterada — mesmos endpoints, mesma autorização, mesma lógica.
