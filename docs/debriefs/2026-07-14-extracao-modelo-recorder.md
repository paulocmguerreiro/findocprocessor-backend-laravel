# Debrief: Extração — registo de passos de IA + histórico unificado (model + recorder)

**Issue:** #94
**Branch:** feat/extracao-modelo-recorder
**Data:** 2026-07-14
**Commits:** 14 commits (main..HEAD)

## O que foi implementado

Modelo de dados e recorder para a dimensão de extracção por IA do `Documento`, ortogonal ao
`EstadoDocumento` de negócio (que fica inalterado):

- 2 enums novos em `App\Shared\Enums`: `EtapaExtracao` (6 casos — `Pendente`, `NecessitaOcr`,
  `TextoPronto`, `NecessitaCloud`, `Concluido`, `Falhado`) e `ResultadoEtapa` (`Sucesso`, `Falha`,
  `EmCurso`).
- Tabela + Model + Factory `ExtracaoDocumento` — relação 1-1 com `Documento` (`id_documento`
  único, `cascadeOnDelete`), índice composto `(etapa_extracao, extracao_reclamada_em)` para o
  futuro `Schedule` de orquestração. Sem `RegistaActividade` (dados operacionais/PII).
- `etapas_documento` ganha colunas nullable `passo`/`resultado` — `NULL` em ambas continua a
  significar linha de negócio (invariante preservado); `estado` mantém-se sempre não-nulo.
- Relação `Documento::extracao(): HasOne`.
- Recorder `RegistarEtapaExtracaoAction` (+ `RegistarEtapaExtracaoDto`, Value Object que valida
  `motivo` obrigatório quando `resultado === Falha`) — dentro de `DB::transaction()`, faz upsert em
  `extracoes_documento` e cria uma linha `EtapaDocumento` com `passo`/`resultado` preenchidos;
  invalida `TagCache::Documentos`. Sem `Gate::authorize` (acção de sistema, sem par HTTP) e sem
  passar pelo `ExecutorTransicaoDocumento` (nunca muda `EstadoDocumento`).
- `EtapaDocumentoResource` expõe `passo`/`resultado`; `DocumentoResource` expõe `etapa_extracao`
  via `whenLoaded('extracao')`. `texto_extraido`/`dados_json` nunca saem de nenhum Resource (RGPD).
- `ReprocessarDocumentoAction` (`Erro → AguardaEnvio`) passa a resetar a linha `extracoes_documento`
  associada (`Pendente`, tentativas a 0, texto/dados a `null`) na mesma transacção da transição de
  estado — atomicidade preservada; documentos sem linha de extracção não geram uma nova (`update()`,
  nunca `create()`).

## Ficheiros alterados

| Ficheiro | Tipo de alteração | Notas |
| -------- | ----------------- | ----- |
| `app/Shared/Enums/EtapaExtracao.php` | criado | 6 casos, backed string |
| `app/Shared/Enums/ResultadoEtapa.php` | criado | 3 casos, backed string |
| `database/migrations/2026_07_14_100000_create_extracoes_documento_table.php` | criado | tabela `extracoes_documento` |
| `app/Models/ExtracaoDocumento.php` | criado | `HasUuids`, sem `RegistaActividade` |
| `database/factories/ExtracaoDocumentoFactory.php` | criado | states pelos 6 casos de `EtapaExtracao` + `reclamada()` |
| `database/migrations/2026_07_14_100100_add_passo_resultado_to_etapas_documento_table.php` | criado | colunas nullable |
| `app/Models/EtapaDocumento.php` | alterado | `passo`/`resultado` em `@property-read`, `#[Fillable]`, `casts()` |
| `database/factories/EtapaDocumentoFactory.php` | alterado | state `passoIa()` |
| `app/Models/Documento.php` | alterado | relação `extracao(): HasOne` |
| `app/Features/Documento/RegistarEtapaExtracao/RegistarEtapaExtracaoDto.php` | criado | VO, invariante `Falha` exige `motivo` |
| `app/Features/Documento/RegistarEtapaExtracao/RegistarEtapaExtracaoAction.php` | criado | recorder, upsert + histórico + cache |
| `app/Features/Documento/EtapaDocumentoResource.php` | alterado | expõe `passo`/`resultado` |
| `app/Features/Documento/DocumentoResource.php` | alterado | expõe `etapa_extracao` (`whenLoaded`) |
| `app/Features/Documento/Reprocessar/ReprocessarDocumentoAction.php` | alterado | reset atómico de `extracoes_documento` |
| `database/migrations/2026_07_14_120000_add_cascade_on_update_to_domain_fks.php` | criado | ver "Desvios ao Plano" |
| `tests/Unit/Models/ExtracaoDocumentoTest.php` | criado | casts, relação, unicidade, cascade |
| `tests/Unit/Models/EtapaDocumentoTest.php` | alterado | casts `passo`/`resultado` (incl. `null`) |
| `tests/Unit/Models/DocumentoTest.php` | alterado | relação `extracao()` |
| `tests/Unit/Features/Documento/RegistarEtapaExtracaoDtoTest.php` | criado | invariante `Falha` |
| `tests/Unit/Features/Documento/RegistarEtapaExtracaoActionTest.php` | criado | upsert, append-only, tentativas, lease, rollback, sem Gate |
| `tests/Unit/Features/Documento/DocumentoResourceTest.php` | alterado | `etapa_extracao` presente/ausente, PII nunca exposta |
| `tests/Unit/Features/Documento/ReprocessarDocumentoActionTest.php` | alterado | reset com/sem linha, rollback |
| `tests/Feature/Features/Documento/ReprocessarDocumentoTest.php` | alterado | equivalente via HTTP |
| `tests/Feature/Features/Documento/VerDocumentoTest.php` | alterado | `etapa_extracao` no payload HTTP |
| `docs/system_spec/**` | criado/alterado | ver secção "SYSTEM_SPEC a actualizar" |
| `.claude/commands/*.md`, `.claude/skills/*.md` | alterado | ver "Desvios ao Plano" — fix de processo |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| Recorder faz `updateOrCreate` que substitui totalmente `texto_extraido`/`dados_json` (sem merge/delta) | Merge parcial dos campos preenchidos | Contrato mais simples e sem ambiguidade; o futuro orquestrador é responsável por enviar sempre o valor completo pretendido (documentado no PHPDoc da Action) |
| `RegistarEtapaExtracaoAction` não passa pelo `ExecutorTransicaoDocumento` | Reutilizar o executor mesmo sem mudança de `EstadoDocumento` | O executor valida transições `De→Para` de negócio e move ficheiro — nenhuma das duas coisas se aplica aqui; forçar a passagem introduziria acoplamento artificial |
| `ReprocessarDocumentoAction` usa `update()` (nunca `create()`/`upsert()`) para resetar `extracoes_documento` | `updateOrCreate()` | Documentos que nunca entraram na dimensão de extracção (ex.: erro de scan de malware em `Pendente`) não devem ganhar uma linha `extracoes_documento` só por serem reprocessados |
| `texto_extraido`/`dados_json` nunca em nenhum Resource | Expor `texto_extraido` truncado no `DocumentoResource` | RGPD — mitigação já decidida no Brief; só `EtapaDocumento.motivo` (já existente) fica exposto |
| system_spec: `01-features/documento.md` (289 linhas) desdobrado em `documento.md` (superfície HTTP) + `documento-pipeline.md` (pipeline background) | Manter tudo num único ficheiro, aceitando > 200 linhas | Ficheiro já ultrapassava o limiar informativo de 200 linhas antes desta issue; a fronteira HTTP/pipeline é uma divisão natural e já usada implicitamente no texto |

## Desvios ao Plano

- **`database/migrations/2026_07_14_120000_add_cascade_on_update_to_domain_fks.php`** não consta do
  Plano da Issue #94 — é uma alteração de fundação (`cascadeOnUpdate()` em todas as FKs de domínio,
  preparação para uma futura reconciliação/agregação de bases de dados que remapeie UUIDs) commitada
  directamente nesta branch antes das tarefas da issue, com a sua própria actualização de
  `system_spec` (`03-models/00-convencoes-models.md`, `documento.md`, `etapa-documento.md`,
  `tipo-documento.md`, `user.md`) já incluída no commit `fa2ce25`. Mantida nesta branch por já estar
  commitada e testada; não é código gerado pela Issue #94.
- **Fixes de processo em `.claude/commands/`/`.claude/skills/`** (`936f07f`, `9bd79d9`) — durante a
  Fase 3a desta issue, identificou-se duplicação da tarefa de "documentação system_spec" entre o
  Plano (Fase 2) e o `/documenta-implementacao` (Fase 3a), e falta de checklist de verificação em
  `actualiza-spec`. Corrigido no próprio processo (não é código da app) para não repetir o problema
  em issues futuras — decisão tomada durante esta sessão, fora do âmbito literal do Plano de #94.
- Fora isso, nenhum desvio — as 8 tarefas do Plano foram implementadas como especificado.

## Aprendizagens

O ponto mais claro desta issue foi perceber que nem toda a Action que grava estado em `Documento`
precisa de passar pelo `ExecutorTransicaoDocumento`/`RegraTransicaoEstado` — esse mecanismo existe
especificamente para validar transições de **uma máquina de estados discreta** (`EstadoDocumento`,
7 casos, mapa `De→Para`) e mover o ficheiro em disco. `RegistarEtapaExtracaoAction` grava estado
(`etapa_extracao`) mas essa dimensão não é uma máquina de estados com transições proibidas — é mais
parecida com um contador/ponteiro de progresso que qualquer chamada pode reescrever livremente,
desde que dentro de uma transacção. Modelar as duas dimensões (negócio vs. extracção) como
completamente ortogonais — cada uma com o seu próprio mecanismo de escrita, sem forçar as duas a
partilhar o mesmo executor só porque ambas tocam no mesmo agregado (`Documento`) — evitou o que
teria sido um acoplamento artificial entre um validador de máquina de estados e uma coluna que não
é uma máquina de estados. Ficou também mais claro o valor de `EtapaDocumento` como tabela
append-only genuinamente dupla: a mesma tabela regista tanto transições de negócio (`passo`/
`resultado` a `null`) como passos de IA (`estado` repetido, `passo`/`resultado` preenchidos), sem
precisar de duas tabelas de histórico separadas — o `estado` presente em cada linha de IA ancora
sempre esse passo ao `EstadoDocumento` em que o `Documento` estava nesse instante, o que dá o feed
cronológico único pedido pela issue sem juntar duas tabelas em runtime.

## SYSTEM_SPEC a actualizar

- `docs/system_spec/03-models/extracao-documento.md` — novo (Model, migration, Factory)
- `docs/system_spec/03-models/etapa-documento.md` — colunas `passo`/`resultado`, Factory `passoIa()`
- `docs/system_spec/03-models/documento.md` — relação `extracao()`
- `docs/system_spec/02-shared/enums.md` — `EtapaExtracao`, `ResultadoEtapa`
- `docs/system_spec/01-features/documento.md` / `documento-pipeline.md` — `RegistarEtapaExtracaoAction`,
  ripple em `ReprocessarDocumentoAction`, modelo de 2 dimensões (movido de `02-shared/estados.md`)
- `docs/system_spec/04-infra/queue-jobs.md` — nota sobre o ponto de invocação para o futuro orquestrador
- `docs/system_spec/04-infra/external-apis.md` — referência ao novo modelo
- `docs/system_spec/00-index.md` — `ExtracaoDocumento` + `documento-pipeline.md` na tabela

(Já executado nesta sessão — ver secção seguinte.)

## Verificação final

- [x] Linter a verde (`vendor/bin/pint --dirty`, sem alterações pendentes)
- [x] Testes a verde (974/974, 100% coverage/types, arch 8/8, Larastan nível 9 zero erros — via
      `docker compose exec app composer test`)
- [x] Nenhum dado sensível em logs
- [x] Nenhum segredo em código
