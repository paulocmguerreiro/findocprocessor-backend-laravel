# Debrief: Extração — orquestradores Schedule (`extracao:*`) sobre máquina de estados unificada

**Issue:** #111
**Branch:** feat/extracao-orquestradores-schedule
**Data:** 2026-07-17
**Commits:** 16 commits

## O que foi implementado

A "cola" final do pipeline de extracção — orquestradores agendados que conduzem `Documento`s
através de `Pendente → AnaliseTexto → [AnaliseOcr] → AnaliseIaLocal → [AnaliseCloud] → Processado`,
ligando pela primeira vez os motores puros (`app/Infrastructure/`, #96/#97/#90) às Actions de
transição (#94/#110):

- **Flexibilização do DTO de conclusão** — `TransicionarProcessadoDocumentoDto` aceita
  `idFornecedor`/`idCliente`/`valor`/`dataDocumento` nulos (documentos "parciais": extractos,
  avisos); `RegraNomearProcessado` ganha fallbacks (nome extraído / `created_at`) para o nome
  canónico não partir nesses casos.
- **Reset de tentativas técnicas** — `RegraReporTentativasExtracao`, invocada pelo
  `ExecutorTransicaoDocumento` em todo avanço correcto de etapa (nunca em `Erro`).
- **Reivindicação por lease** — `Reivindicar/`+`Triar/` agrupados em `Atribuicao/`;
  `ReivindicarDocumentoEmEtapaAction` reclama sob `lockForUpdate` com TTL
  (`extracao_reclamada_em`), criando a linha `ExtracaoDocumento` se ausente.
- **Reconciliação de entidades por lado** — `RegraReconciliarEntidadesDocumento` resolve
  `id_fornecedor`/`id_cliente` (empresa mãe singleton / find-or-create por NIF / `null`).
- **4 orquestradores de etapa** — `ProcessarAnaliseTexto` (parser + detecção de imagem),
  `ProcessarAnaliseOcr` (Tesseract), `ProcessarAnaliseIaLocal` e `ProcessarAnaliseCloud` (guarda de
  camada + veredicto), com duas Actions partilhadas extraídas para evitar duplicação:
  `ConcluirExtracaoDocumentoAction` (reconciliação + transição para `Processado`, partilhada por
  IA-local/IA-cloud) e `RegistarFalhaTecnicaExtracaoAction` (contador + registo + `Erro` na 3ª
  falha, partilhada pelos 4 orquestradores).
- **5 Commands `extracao:*`** (`EtapaExtracaoCommand` como base) + agendamento em
  `routes/console.php` (`everyMinute()`/`everyFiveMinutes()`, `withoutOverlapping()`).
- **Upload** — TIFF/BMP/WEBP aceites + limite 10 MB → 50 MB.
- **Infra Docker** — serviço `scheduler`, limites PHP (`upload_max_filesize`/`post_max_size`) e
  `clamd.conf` alinhados a 50 MB.

## Ficheiros alterados

| Ficheiro | Tipo de alteração | Notas |
| -------- | ------------------ | ----- |
| `app/Features/Documento/Atribuicao/Reivindicar/ReivindicarDocumentoPendenteAction.php` | movido | de `Reivindicar/` (refactor estrutural, limiar de 3 Actions) |
| `app/Features/Documento/Atribuicao/Triar/TriarDocumentoPendenteAction.php` | movido | de `Triar/` |
| `app/Features/Documento/Atribuicao/ReivindicarDocumentoEmEtapa/ReivindicarDocumentoEmEtapaAction.php` | criado | lease + `lockForUpdate` sobre `ExtracaoDocumento` |
| `app/Features/Documento/Operacoes/Transicao/RegraReporTentativasExtracao.php` | criado | reset do contador no funil único de transição |
| `app/Features/Documento/Operacoes/Transicao/ExecutorTransicaoDocumento.php` | alterado | invoca `RegraReporTentativasExtracao` |
| `app/Features/Documento/Operacoes/Transicao/RegraNomearProcessado.php` | alterado | fallbacks de nome/data |
| `app/Features/Documento/Operacoes/TransicionarProcessado/TransicionarProcessadoDocumentoDto.php` | alterado | campos nullable gated por `espera_*` |
| `app/Features/Documento/Operacoes/TransicionarProcessado/TransicionarProcessadoDocumentoAction.php` | alterado | `findOrFail` condicional |
| `app/Features/Documento/Processamento/ReconciliarEntidades/RegraReconciliarEntidadesDocumento.php` + `ResultadoReconciliacaoEntidades.php` | criados | resolução por lado |
| `app/Features/Documento/Processamento/ConcluirExtracao/ConcluirExtracaoDocumentoAction.php` | criado | conclusão partilhada (reconciliação + transição) |
| `app/Features/Documento/Processamento/RegistarFalhaTecnicaExtracao/RegistarFalhaTecnicaExtracaoAction.php` | criado | contador + `Erro` na 3ª falha, partilhado |
| `app/Features/Documento/Processamento/ProcessarAnaliseTexto/ProcessarAnaliseTextoDocumentoAction.php` | criado | orquestrador parser + salto de imagem |
| `app/Features/Documento/Processamento/ProcessarAnaliseOcr/ProcessarAnaliseOcrDocumentoAction.php` | criado | orquestrador Tesseract |
| `app/Features/Documento/Processamento/ProcessarAnaliseIaLocal/ProcessarAnaliseIaLocalDocumentoAction.php` | criado | orquestrador IA local + guarda de camada |
| `app/Features/Documento/Processamento/ProcessarAnaliseCloud/ProcessarAnaliseCloudDocumentoAction.php` | criado | orquestrador IA cloud + guarda de camada |
| `app/Console/Commands/Extracao/EtapaExtracaoCommand.php` | criado | base abstracta dos 5 commands |
| `app/Console/Commands/Extracao/Executar{Scan,Parser,Tesseract,IaLocal,IaCloud}ExtracaoCommand.php` | criados | 5 Commands `extracao:*` |
| `routes/console.php` | alterado | agendamento `Schedule` |
| `app/Features/Documento/RecepcaoUpload/ReceberUploadDocumentoRequest.php` | alterado | TIFF/BMP/WEBP + `max:51200` |
| `app/Features/Documento/Corrigir/CorrigirDocumentoAction.php`, `Criar/RegistarDocumentoManualAction.php` | alterados | acompanham a flexibilização do DTO/naming |
| `config/extracao.php` | alterado | `EXTRACAO_TTL_LEASE` com `config()->integer()` |
| `app/Providers/AppServiceProvider.php` | alterado | binding `ContratoClienteIA` |
| `Dockerfile`, `compose.yaml`, `docker/clamav/clamd.conf`, `.env.example`, `README.md` | alterados/criado | serviço `scheduler`, limites 50 MB, docs de ambiente |
| 20 ficheiros em `tests/Unit/` e `tests/Feature/` | criados/alterados | cobertura dual (unit + feature) por Action/Command |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ------------------------ | ------------ |
| Extrair `ConcluirExtracaoDocumentoAction` partilhada entre `ProcessarAnaliseIaLocal`/`ProcessarAnaliseCloud` | Duplicar reconciliação+transição em cada orquestrador | Evita duplicar lógica entre Actions (regra do CLAUDE.md); os dois orquestradores convergem no mesmo passo final |
| Extrair `RegistarFalhaTecnicaExtracaoAction` partilhada pelos 4 orquestradores | Repetir contador+`Erro` em cada orquestrador | Mesmo motivo — o tecto de tentativas é uma regra transversal, não específica de etapa |
| Colapsar `Reivindicar`+`Triar`+`ReivindicarDocumentoEmEtapa` em `Atribuicao/` | Manter `Reivindicar/` e `Triar/` como pastas soltas | Limiar de 3 Actions da mesma categoria semântica (reivindicação) atingido — decisão já prevista no Plano |
| Base `EtapaExtracaoCommand` (sufixo `Command`) para os 5 commands | Base sem sufixo (`ComandoExtracao`) | ArchTest de nomenclatura de Commands exige o sufixo mesmo na classe abstracta base — corrigido em commit isolado após falha do arch test |
| `config('extracao.ttl_lease')` com `->integer()` | `env()` directo (retorna `string`/`mixed`) | `EXTRACAO_TTL_LEASE` vinha como string do `.env`, quebrando comparações aritméticas do lease; `config()->integer()` força o cast |
| Reset de `extracao_tentativas` centralizado no `ExecutorTransicaoDocumento` | Repor o contador em cada orquestrador antes de transicionar | Funil único de todas as transições (já existente) — evita espalhar a regra por 4 Actions, consistente com o Brief/Plano |

## Desvios ao Plano

- **Duas Actions partilhadas não explícitas no Plano** (`ConcluirExtracaoDocumentoAction`,
  `RegistarFalhaTecnicaExtracaoAction`): o Plano descrevia o comportamento por orquestrador; na
  implementação, a lógica comum a IA-local/IA-cloud (conclusão) e aos 4 orquestradores (falha
  técnica) foi extraída para evitar duplicação — consistente com "Não duplicar lógica entre
  Actions" do CLAUDE.md, não é uma alteração de âmbito.
- **Commit adicional de correcção arquitectural** (`c94a47f`): a base dos Commands precisou do
  sufixo `Command` (`EtapaExtracaoCommand`) para passar o ArchTest — não estava explicitado no
  Plano que a classe base também estaria sujeita à regra de nomenclatura.
- **Commit adicional de correcção de tipo** (`12b1993`): `EXTRACAO_TTL_LEASE` precisou de
  `config()->integer()` — descoberto ao correr os testes de lease (o valor do `.env` chegava como
  `string`); aproveitado para reforçar o teste de restauro de sessão em `ConcluirExtracaoDocumentoAction`.

## Aprendizagens

- **Orquestrar é só mais uma Action.** Não foi preciso nenhuma camada nova ("Orchestrator",
  "Service") — os 4 orquestradores de etapa são Actions normais que compõem outras Actions
  (reivindicação, motores de `Infrastructure/`, transição). Vertical Slice não distingue "lógica de
  negócio" de "lógica de coordenação": ambas vivem em Actions, e Actions chamam Actions livremente
  quando isso evita duplicação — o limite não é "uma Action não invoca outra", é "não duplicar
  lógica entre Actions".
- **A regra do limiar de reagrupamento (`Reivindicar`+`Triar` → `Atribuicao/`) só se revela a
  posteriori.** O Plano já antecipava a necessidade (3ª Action da mesma categoria semântica), mas só
  ficou claro ao escrever `ReivindicarDocumentoEmEtapaAction` que as três Actions partilhavam o
  mesmo conceito de domínio (reivindicação/atribuição de trabalho), não apenas o mesmo `Documento`.
- **ArchTests não param nas folhas.** A regra "Commands terminam em `Command`" aplicava-se também à
  classe abstracta base (`EtapaExtracaoCommand`), não só aos 5 Commands concretos — um lembrete de
  que convenções de nomenclatura em PHP (incl. Larastan/Rector) tipicamente não distinguem
  base/concreta.
- **`env()` vs `config()->integer()`**: variáveis de ambiente chegam sempre como `string` (ou
  `null`); usar directamente em aritmética/comparação de datas (TTL do lease) só falha em runtime
  sob dados reais — reforça por que o CLAUDE.md exige eliminar `mixed` e tipar explicitamente em
  vez de confiar no cast implícito do PHP.

## SYSTEM_SPEC a actualizar
- `docs/system_spec/01-features/documento-pipeline.md` — orquestradores, `ConcluirExtracaoDocumentoAction`, `RegistarFalhaTecnicaExtracaoAction`, colapso `Atribuicao/`
- `docs/system_spec/01-features/documento.md` — upload: tipos TIFF/BMP/WEBP + limite 50 MB
- `docs/system_spec/02-shared/estados.md` — reset de `extracao_tentativas` no `ExecutorTransicaoDocumento`
- `docs/system_spec/04-infra/queue-jobs.md` — 5 Commands `extracao:*` + agendamento `Schedule`
- `docs/system_spec/04-infra/ambiente-docker.md` — serviço `scheduler`, limites PHP/`clamd` a 50 MB
- `docs/system_spec/04-infra/malware.md` — `clamd.conf` (`StreamMaxLength`/`MaxScanSize`/`MaxFileSize`)
- `docs/system_spec/04-infra/extracao-texto.md` — novos formatos de imagem aceites
- `docs/system_spec/06-config.md` — `EXTRACAO_TTL_LEASE`, `FILESYSTEM_ALLOWED_EXTENSIONS`, `FILESYSTEM_MAX_FILE_SIZE`
- `docs/system_spec/00-index.md` — entradas para os novos ficheiros/Actions

## Verificação final
- [x] Linter a verde (incluído em `composer test` — Pint + Rector `--dry-run` sem erros)
- [x] Testes a verde (`composer test` via `docker compose exec app` — 1146 passed, coverage 100%)
- [x] Nenhum dado sensível em logs
- [x] Nenhum segredo em código
