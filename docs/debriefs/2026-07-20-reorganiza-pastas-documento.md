# Debrief: reorganiza estrutura de pastas da feature Documento (WRN-037)

**Issue:** #114
**Branch:** refactor/reorganiza-pastas-documento
**Data:** 2026-07-20
**Commits:** 6 commits de refactor

## O que foi implementado

Refactor estrutural puro da feature `Documento`: aplicação da regra de granularidade pasta-por-Action
(`02-shared/estrutura-subpastas-features.md`) ao código real. 25 ficheiros de código + 1 de teste
renomeados/movidos, com actualização de namespaces e de todos os `use` consumidores (Controller, 5
Console Commands, cross-imports entre Actions, ~24 testes). Zero alteração de comportamento — os mesmos
testes passam sem mudança de asserções.

## Ficheiros alterados

| Ficheiro / grupo | Tipo de alteração | Notas |
| ---------------- | ----------------- | ----- |
| `Pesquisa/Ver/{Action,Request}` → `Pesquisa/` | movido (solto) | CA-01, 2 artefactos <3 |
| `Atribuicao/Reivindicar/*`, `Atribuicao/ReivindicarDocumentoEmEtapa/*` → `Atribuicao/` | movido (solto) | CA-01; `Atribuicao/` fica com 2 Actions (CA-05, não dissolvida) |
| `Processamento/ProcessarAnalise{Texto,Ocr,IaLocal,Cloud}/*` → `Processamento/` | movido (solto) | CA-01 |
| `Processamento/RegistarEtapaExtracao/{Action,Dto}`, `RegistarFalhaTecnicaExtracao/*` → `Processamento/` | movido (solto) | CA-01 |
| `Processamento/ReconciliarEntidades/{Regra,Resultado}` → `Processamento/ConcluirExtracao/` | movido (fusão) | CA-02; `ConcluirExtracao/` fica com 3 ficheiros |
| `Atribuicao/Triar/TriarDocumentoPendenteAction` → `Processamento/ProcessarAnaliseMalwareDocumentoAction` | renomeado + movido | CA-03; classe, teste, property `$processarAnaliseMalware`, comentários |
| `Operacoes/TransicoesEstado/` (nova) ← 5×`MarcarAnalise*`, `MarcarErro`+Dto, `MarcarPerigoso`+Dto, `TransicionarProcessado`+Dto | criado + movido | CA-04; 8 atalhos de transição soltos |
| `DocumentoController.php` | alterado | `use` de `Pesquisa\Ver` |
| `app/Console/Commands/Extracao/Executar*ExtracaoCommand.php` (5) | alterado | `use` de `ProcessarAnalise*` / `Reivindicar` |
| Cross-imports entre Actions de `Processamento/` | alterado | namespaces encurtados; Pint removeu `use` de mesmo-namespace |
| ~24 testes em `tests/Unit/Features/Documento/` | alterado | só `use`; 1 renomeado (`Triar…Test` → `ProcessarAnaliseMalware…Test`) |

## Decisões tomadas

| Decisão | Alternativa considerada | Porquê esta |
| ------- | ----------------------- | ----------- |
| Nome da subpasta nova = `TransicoesEstado/` | `Marcacoes/` (proposto na issue), `Etapas/`, `Estados/` | O nome descreve a **intenção** (transição de estado/etapa), não o mecanismo ("marcar"); distingue-se de `Operacoes/Transicao/` (o motor `Executor`+`Regra*`). Decisão do utilizador em Checkpoint A. |
| Consolidar **os 8** atalhos (incl. `MarcarErro`/`MarcarPerigoso`) em `Operacoes/TransicoesEstado/` | Manter estado-alvo do spec: `MarcarErro`/`MarcarPerigoso` → `Processamento/Anomalias/` | A issue #114 é a decisão mais recente e supersede a nota "Anomalias" acabada de gravar em WRN-037; agrupar todos os atalhos de transição num só sítio é mais coerente que separar por "anomalia vs pipeline". |
| Property `$triar` → `$processarAnaliseMalware` em `ReivindicarDocumentoPendenteAction` | Manter `$triar` | Regra do escuteiro + convenção de injecção de Actions (`$marcarErro`, `$transicionarProcessado`); ficheiro tocado pelo rename. |
| Deixar `$dados` intacto em `MarcarErro`/`MarcarPerigoso` | Renomear para `$dadosErro`/`$dadosPerigoso` | Issue é de movimento puro (plano proíbe alterar assinaturas/corpos); surgido em checkpoint, utilizador optou por não corrigir aqui. |
| Commits isolados por CA | Um commit único | "Fluxo ao mover uma Action" exige commit de refactor sem lógica nova; facilita revisão/reversão. |

## Desvios ao Plano

Nenhum desvio de âmbito. Um ajuste mecânico não previsto: o `pint --dirty` não apanhou os ficheiros
**renomeados+modificados** na T3 (git trata rename como par delete/add), deixando `use` de
mesmo-namespace por remover; corrigido com `vendor/bin/pint <dir>` (não-dirty) na T4, incluído no
commit da fusão. Sem impacto funcional.

## Aprendizagens

- **Granularidade pasta-por-Action (Vertical Slice).** A regra de coesão tem dois níveis: o limiar de 3
  decide quando um *grupo* de Actions ganha subpasta semântica (`Processamento/`, `Operacoes/`); um
  nível abaixo, o mesmo limiar de 3 (agora de *artefactos próprios*) decide quando uma *Action
  individual* merece pasta própria vs ficheiro solto. Aplicá-lo a 26 Actions reais mostrou que a maioria
  das pastas pasta-por-Action com 1 ficheiro eram ruído de navegação — o Vertical Slice quer a Feature
  o mais flat possível, subpastas só quando o volume o justifica.
- **Nomear pela intenção, não pelo mecanismo.** `Marcacoes/` (o verbo "marcar") descrevia *como* as
  Actions funcionam; `TransicoesEstado/` descreve *o que* fazem no domínio (transição de estado). Numa
  arquitectura orientada a casos de uso, o vocabulário das pastas deve ser o do negócio.
- **O refactor de localização é seguro por causa do autoload.** Como as Actions se resolvem por
  PSR-4/reflection (sem binding explícito no Service Container), o único ponto de falha é
  `namespace`/`use` desalinhado — e a bateria de testes dual (Unit + Feature) + Larastan nível 9
  apanha isso a 100%. Confirma que uma feature bem testada permite reorganização estrutural sem medo.
- **`git mv` + Pint têm uma interacção não-óbvia:** `pint --dirty` compara contra o HEAD e pode não
  processar ficheiros renomeados, pelo que a limpeza de imports redundantes exige um Pint não-dirty
  sobre a pasta afectada após colapsar namespaces.

## SYSTEM_SPEC a actualizar

- `docs/system_spec/01-features/documento.md` — caminhos/namespaces das Actions (Pesquisa/Ver soltos,
  Atribuicao sem subpastas, ProcessarAnaliseMalware ex-Triar).
- `docs/system_spec/01-features/documento-pipeline.md` — namespaces do pipeline (`ProcessarAnalise*`
  soltos, `Marcar*`/`TransicionarProcessado` agora em `Operacoes/TransicoesEstado/`, `RegistarEtapa*`).
- `docs/system_spec/01-features/documento-reconciliacao.md` — `ReconciliarEntidades` agora em
  `ConcluirExtracao/`.
- `docs/system_spec/02-shared/estrutura-subpastas-features.md` — **reconciliação**: "Exemplo real
  validado" de estado-alvo → estado real; apagar/reescrever as notas "Anomalias" e a linha de
  vocabulário que lista `MarcarAnalise*` sob `Processamento/`, reflectindo `Operacoes/TransicoesEstado/`.
- `docs/system_spec/03-models/extracao-documento.md`, `04-infra/malware.md`, `04-infra/queue-jobs.md`,
  `04-infra/transactions.md` — actualizar caminhos/namespaces referenciados.

## Verificação final

- [x] Linter a verde (Pint + Rector dry-run via `composer test:lint`; Rector apply 0 alterações)
- [x] Testes a verde (`composer test` — 100% coverage, 100% type-coverage, Larastan nível 9, arch)
- [x] `checkpoint:scan` — 0 FAILs (4 warns supply-chain pré-existentes, sem alteração de dependências)
- [x] Nenhum dado sensível em logs (refactor não toca em logging)
- [x] Nenhum segredo em código
