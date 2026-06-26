# Brief: Documento — lógica (máquina de estados: Actions de transição + listagem + Regra* + Events)

**Issue:** #57
**Data:** 2026-06-26
**Branch:** feat/documento-logica-estados

## Contexto

As issues #45 (Model `Documento` + state objects read-only + DTOs sem `fromRequest()`) e #56
(`EtapaDocumento`, histórico append-only) montaram a **infra-estrutura passiva** do documento: as
colunas, os 7 state objects, a relação `historico` e a tabela de etapas. Falta a **camada que
realmente move o documento pelo ciclo de vida** — a máquina de estados.

Esta issue implementa essa camada exclusivamente com **Actions de transição** (padrão do projecto,
como `CriarEntidadeAction`/`ConverterEmEmpresaMaeAction`). Não há camada "Serviço": cada Action
orquestra `Gate::authorize()` (fora) + `DB::transaction()` (dentro: invariantes `Regra*` +
persistência do estado + movimento de ficheiro entre discos + escrita de uma linha `EtapaDocumento`)
+ emissão de um Event de domínio after_commit.

O princípio central é a **porta única de entrada**: a mesma Action serve o utilizador (HTTP) e o
futuro mecanismo de extracção (IA/OCR, issue diferida, via Job). As Actions recebem DTOs e ignoram
como os dados foram obtidos — isto mantém a extracção desacoplada e substituível. Por isso a issue
**absorve a antiga camada "Persistência"**: a listagem resolve-se direto no Eloquent com os scopes do
Model (`whereEstado`, etc.), **sem Repository**.

## O que muda

**Domínio / aplicação (`app/Features/Documento/`)** — núcleo da issue:
- **Actions de transição** (uma slice por operação), correspondentes ao grafo de transições da issue:
  registo manual, recepção de upload, marcar aguarda-envio, marcar perigoso, marcar enviado, marcar
  aguarda-resposta, transicionar para processado, marcar erro, reprocessar, corrigir, eliminar.
- **`ListarDocumentosAction`** — `cursorPaginate` + scopes + cache, direto no Eloquent (sem
  Repository), com enum de campo de ordenação (padrão `CampoOrdenacaoEntidades`).
- **Classes `Regra*`** (invariantes injectados, padrão `RegraUnicidadeEmpresaMae`):
  `RegraTransicaoEstado` (valida De→Para contra mapa central), `RegraMoverFicheiro` (move o ficheiro
  entre discos e mantém `disco_storage`/`nome_ficheiro_storage` consistentes com `status`),
  `RegraNomearProcessado` (gera `yyyy-mm-dd-{slug-fornecedor}-{slug-categoria}.{ext}`).
- **DTOs de transição** `final readonly` com validação de invariantes no construtor + `fromRequest()`;
  e adição de `fromRequest()` aos DTOs manuais já existentes da #45.
- **Events de domínio** after_commit (`DocumentoProcessado`, `DocumentoMarcadoErro`,
  `DocumentoMarcadoPerigoso`, `DocumentoReprocessado`, …) — desacoplam efeitos futuros.

**HTTP (`app/Features/Documento/` + `routes/api.php`)** — só para as Actions expostas (`HTTP` na
tabela do grafo): `DocumentoController` (zero lógica), rotas, FormRequests com autorização dupla
(`Gate` no Request e na Action) e `messages()` em PT; leitura do histórico via `DocumentoResource`
(`whenLoaded('historico')`) ou endpoint próprio.

**Infra leve:** cada transição grava uma `EtapaDocumento` na mesma transacção; novo caso
`TagCache::Documentos` para a listagem; os discos `entrada/enviado/processado/erro/perigoso` já
existem em `config/filesystems.php`.

**SYSTEM_SPEC:** novo `01-features/documento.md`; `02-shared/regras-negocio.md`,
`02-shared/estados.md`, `04-infra/queue-jobs.md`, `05-routes/documento.md`, `00-index.md`,
`./openapi.yaml` (Fase 3a).

## O que NÃO muda

- **Mecanismo de extracção** (IA/OCR/outras), **pré-scan de segurança real** e **hierarquia de
  fallback** (OCR→modelo A→B→C) — issue separada diferida. Aqui só existem as Actions que ela
  invocará; `MarcarPerigosoAction` e `ReprocessarAction` apenas **aceitam e propagam** o input (motivo,
  `modo`), sem lógica de scan nem catálogo de ferramentas.
- **Autenticação/autorização real** — `DocumentoPolicy` continua stub (`true`).
- **Model `Documento`, state objects, `EtapaDocumento`** — não mudam de forma (#45/#56); apenas se
  **consomem**. Não se adicionam métodos `correct()` aos state objects (continuam read-only).
- **Jobs reais de pipeline** (`WatchInboxJob`, `ProcessBatchJob`) — continuam planeados; aqui só se
  garante que as Actions são invocáveis programaticamente e que os Events são `after_commit`.
- Migrations, factories e a estrutura de discos — já existentes.

## Riscos identificados

- **Atomicidade ficheiro ↔ BD não é garantida pelo `DB::transaction()`.** O filesystem não participa
  na transacção: se o ficheiro for movido entre discos e a transacção der rollback, o ficheiro fica
  num disco inconsistente com o `status` persistido (que reverte). Precisa de decisão sobre a ordem
  (mover ficheiro só após persistência, com compensação em caso de falha) — ver Questões em aberto.
- **Discos configurados com `'throw' => false`** (`config/filesystems.php`): uma operação de
  `move`/`put`/`delete` falhada devolve `false` em silêncio em vez de lançar. `RegraMoverFicheiro` tem
  de **verificar o valor de retorno e lançar explicitamente** para disparar o rollback — caso
  contrário a BD muda de estado e o ficheiro fica para trás sem erro.
- **Mover entre discos distintos não é um `Storage::disk()->move()`.** `move()` opera dentro do mesmo
  disco; atravessar `entrada`→`enviado`→`processado` exige `put(get())` no disco destino + `delete()`
  no origem. Custo e modos de falha diferentes (ficheiro grande, disco cheio).
- **Volume de testes duais.** Cada Action (≈11) precisa de teste Unit (programático, ex.: por Job) +
  Feature (HTTP, só as expostas) + as 3 `Regra*` + Events. Com `composer test` a exigir 100% coverage
  e 100% type coverage, o esforço de testes é o maior risco de prazo da issue.
- **`Auth::id()` é `null` no contexto pipeline.** `EtapaDocumento.id_utilizador` é nullable por
  design (#56), mas as Actions têm de tratar `null` sem assumir utilizador autenticado — coerente com
  a "porta única" HTTP + Job.
- **`RegraTransicaoEstado` central vs. exaustividade.** O mapa De→Para tem de cobrir todas as
  transições do grafo e rejeitar as inválidas com excepção tipada; um caso omisso pode bloquear uma
  transição legítima ou permitir uma ilegítima. Risco de divergência face ao grafo da issue.
- **Naming das Actions.** O grafo da issue usa forma curta (`CorrigirAction`, `EliminarAction`,
  `ReprocessarAction`) mas as features planeadas no `00-index.md` usam forma longa
  (`CorrigirDocumentoAction`, `EliminarDocumentoAction`, `ReprocessarDocumentoAction`) e o padrão do
  projecto inclui a entidade (`CriarEntidadeAction`). Divergir aqui cria inconsistência — ver Questões.

## Questões em aberto

1. **Atomicidade ficheiro↔BD:** estratégia para o caso rollback? Opções: (a) mover o ficheiro
   *dentro* da transacção e aceitar reconciliação manual em falha rara; (b) registar a intenção e
   mover *após* commit, com a BD a guardar disco/nome alvo e um mecanismo de reconciliação; (c) mover
   para destino, persistir, e em caso de excepção tentar mover de volta (compensação best-effort).
   Qual adoptar nesta issue?
2. **Conjunto fechado de Events:** confirmar a lista exacta (a issue termina em "…"). Há transições
   sem Event (`MarcarAguardaEnvio`, `MarcarEnviado`, `MarcarAguardaResposta`)? Emite-se também
   `DocumentoRecebido`/`DocumentoRegistado` no upload/registo manual?
3. **Listeners:** esta issue cria apenas os Events (sem Listeners), ou inclui Listeners stub? Os
   testes assertam `Event::fake()` + dispatch, ou também o efeito de um Listener?
4. **Leitura do histórico:** `DocumentoResource` com `whenLoaded('historico')` **ou** endpoint próprio
   (`GET documentos/{documento}/historico`)? Ou ambos?
5. **Naming das Actions:** forma curta (issue) ou forma longa com `Documento` (índice/convenção do
   projecto)? Decisão única para toda a slice.
6. **Mapeamento DTOs ↔ Actions:** `ActualizarDocumentoDto` (#45) serve a `CorrigirAction`?
   `CriarDocumentoManualDto` serve a `RegistarManualAction`? Confirmar para saber quais DTOs ganham
   `fromRequest()` e quais transições precisam de DTO novo (`MarcarErroDto` com `mensagem_erro`,
   `MarcarPerigosoDto` com `motivo`, `ReprocessarDto` com `modo`, `TransicionarProcessadoDto`).
7. **Fronteira com a feature `Upload` planeada:** `ReceberUploadAction` aqui recebe "ficheiro + hash".
   O cálculo do hash, a validação `multipart/form-data` e a escrita inicial no disco `entrada` ficam
   nesta issue ou na issue de Upload? Qual o limite exacto?
8. **`modo` de reprocessamento:** é um enum novo (`ModoReprocessamento`: modelo/ferramenta) ou string
   livre validada? A issue diz "parametrizado por `modo`" mas a hierarquia de fallback é diferida.
9. **`CampoOrdenacaoDocumentos`:** que campos expõe a ordenação (`data_documento`, `created_at`,
   `status`)? E a listagem aceita filtro por estado via query param mapeado a `whereEstado`?
